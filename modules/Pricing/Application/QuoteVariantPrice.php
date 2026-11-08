<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Application;

use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoteData;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Pricing\Contracts\TaxQualificationKind;
use Litecommerce\Modules\Pricing\Domain\Decimal;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ExchangeRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ProductTaxAssignment;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPrice;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPriceOverride;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * Computes the effective price and line VAT split for a variant, per the
 * rules in docs/knowledge-base/04-pricing-coupons.md ("Money — ustalona
 * polityka obliczeń").
 *
 * The promotional-vs-regular invariant (promotional always lower, even
 * for resulting/gross values) is enforced here, at quote time, not at
 * write time: a rate or price change is never blocked — see the resolved
 * "Ręczna promocja i kurs" decision in 04-pricing-coupons.md. When the
 * computed promotional price is not lower than the regular one for this
 * currency, it is silently dropped for this quote and the item is priced
 * at the regular price instead.
 */
final class QuoteVariantPrice implements PriceQuoter
{
    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly CurrencyRepository $currencies,
    ) {}

    public function quote(int $variantId, string $currencyCode, string $quantity): ?PriceQuoteData
    {
        return $this->quoteMany([$variantId], $currencyCode, $quantity)[$variantId] ?? null;
    }

    public function quoteMany(array $variantIds, string $currencyCode, string $quantity): array
    {
        return $this->quoteLines(
            array_fill_keys(array_values(array_unique(array_map('intval', $variantIds))), $quantity),
            $currencyCode,
        );
    }

    public function quoteLines(array $quantitiesByVariantId, string $currencyCode): array
    {
        $variantIds = array_map('intval', array_keys($quantitiesByVariantId));

        if ($variantIds === []) {
            return [];
        }

        $targetCurrency = $this->currencies->find($currencyCode);
        $baseCurrency = $this->currencies->base();

        if ($targetCurrency === null || $baseCurrency === null) {
            return [];
        }

        $variants = $this->variants->findMany($variantIds, app()->getLocale());
        $basePrices = VariantPrice::query()->whereIn('variant_id', $variantIds)->get()->keyBy('variant_id');
        $overrides = VariantPriceOverride::query()->whereIn('variant_id', $variantIds)
            ->where('currency_code', $currencyCode)->get()->keyBy('variant_id');
        $isBaseCurrency = $currencyCode === $baseCurrency->code;
        $rate = $isBaseCurrency ? null : ExchangeRate::query()->where('currency_code', $currencyCode)->first();
        $decimals = $targetCurrency->decimalPlaces;
        $productIds = array_values(array_unique(array_map(static fn ($variant): int => $variant->productId, $variants)));
        $assignments = ProductTaxAssignment::query()->whereIn('product_id', $productIds)->get()->keyBy('product_id');
        $taxRateIds = $assignments->pluck('tax_rate_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        $taxRates = TaxRate::query()->whereIn('id', $taxRateIds)->get()->keyBy('id');
        $result = [];

        foreach ($variantIds as $variantId) {
            $variant = $variants[$variantId] ?? null;
            $basePrice = $basePrices->get($variantId);

            if ($variant === null || $basePrice === null) {
                continue;
            }

            $assignment = $assignments->get($variant->productId);
            $taxRate = $assignment === null ? null : $taxRates->get($assignment->tax_rate_id);
            $quote = $this->quoteLoaded(
                $variantId,
                $currencyCode,
                (string) $quantitiesByVariantId[$variantId],
                $basePrice,
                $overrides->get($variantId),
                $rate,
                $isBaseCurrency,
                $decimals,
                $taxRate,
            );

            if ($quote !== null) {
                $result[$variantId] = $quote;
            }
        }

        return $result;
    }

    private function quoteLoaded(
        int $variantId,
        string $currencyCode,
        string $quantity,
        VariantPrice $basePrice,
        ?VariantPriceOverride $override,
        ?ExchangeRate $rate,
        bool $isBaseCurrency,
        int $decimals,
        ?TaxRate $taxRate,
    ): ?PriceQuoteData {

        $regular = $this->resolveAmount(
            overrideAmount: $override?->regular_amount,
            overrideMode: $override?->regular_mode,
            baseAmount: $basePrice->regular_amount,
            baseMode: $basePrice->regular_mode,
            isBaseCurrency: $isBaseCurrency,
            rate: $rate,
            decimals: $decimals,
        );

        if ($regular === null) {
            return null;
        }
        [$regularAmount, $regularMode, $regularSource] = $regular;

        $promotional = $basePrice->promotional_amount === null && $override?->promotional_amount === null
            ? null
            : $this->resolveAmount(
                overrideAmount: $override?->promotional_amount,
                overrideMode: $override?->promotional_mode,
                baseAmount: $basePrice->promotional_amount,
                baseMode: $basePrice->promotional_mode,
                isBaseCurrency: $isBaseCurrency,
                rate: $rate,
                decimals: $decimals,
            );

        $qualificationKind = $taxRate === null ? TaxQualificationKind::Percentage : TaxQualificationKind::from($taxRate->kind);
        $percent = '0';

        if ($qualificationKind === TaxQualificationKind::Percentage && $taxRate !== null && $taxRate->percentage !== null) {
            $percent = $taxRate->percentage;
        }

        $promotionalActive = false;
        $dropReason = null;
        $promotionalAmount = null;
        $promotionalMode = null;
        $effectiveAmount = $regularAmount;
        $effectiveMode = $regularMode;
        $effectiveSource = $regularSource;

        if ($promotional !== null) {
            [$promotionalAmount, $promotionalMode, $promotionalSource] = $promotional;
            $regularGross = $this->toGrossForComparison($regularAmount, $regularMode, $percent, $decimals);
            $promoGross = $this->toGrossForComparison($promotionalAmount, $promotionalMode, $percent, $decimals);

            if (Decimal::compare($promoGross, $regularGross) < 0) {
                $promotionalActive = true;
                $effectiveAmount = $promotionalAmount;
                $effectiveMode = $promotionalMode;
                $effectiveSource = $promotionalSource;
            } else {
                $dropReason = 'not_lower_than_regular';
            }
        }

        $lineRounded = Decimal::roundHalfUp(Decimal::mul($effectiveAmount, $quantity), $decimals);

        if ($effectiveMode === PriceMode::Net) {
            $net = $lineRounded;
            $vat = Decimal::roundHalfUp(Decimal::div(Decimal::mul($net, $percent), '100'), $decimals);
            $gross = Decimal::roundHalfUp(Decimal::add($net, $vat), $decimals);
        } else {
            $gross = $lineRounded;
            $vat = Decimal::roundHalfUp(Decimal::div(Decimal::mul($gross, $percent), Decimal::add('100', $percent)), $decimals);
            $net = Decimal::roundHalfUp(Decimal::sub($gross, $vat), $decimals);
        }

        return new PriceQuoteData(
            variantId: $variantId,
            currencyCode: $currencyCode,
            quantity: $quantity,
            unitEffectiveAmount: $effectiveAmount,
            unitEffectiveMode: $effectiveMode,
            unitSource: $effectiveSource,
            regularUnitAmount: $regularAmount,
            promotionalUnitAmount: $promotionalAmount,
            promotionalActive: $promotionalActive,
            promotionalDropReason: $dropReason,
            lineNetAmount: $net,
            lineVatAmount: $vat,
            lineGrossAmount: $gross,
            taxQualificationKind: $qualificationKind,
            taxPercentage: $qualificationKind === TaxQualificationKind::Percentage ? $percent : null,
        );
    }

    /**
     * @return array{0:string,1:PriceMode,2:string}|null [amount, mode, source]
     */
    private function resolveAmount(
        ?string $overrideAmount,
        ?string $overrideMode,
        ?string $baseAmount,
        ?string $baseMode,
        bool $isBaseCurrency,
        ?ExchangeRate $rate,
        int $decimals,
    ): ?array {
        if ($overrideAmount !== null && $overrideMode !== null) {
            return [Decimal::roundHalfUp($overrideAmount, $decimals), PriceMode::from($overrideMode), 'override'];
        }

        if ($baseAmount === null || $baseMode === null) {
            return null;
        }

        if ($isBaseCurrency) {
            return [Decimal::roundHalfUp($baseAmount, $decimals), PriceMode::from($baseMode), 'base'];
        }

        if ($rate === null) {
            return null;
        }

        $converted = Decimal::roundHalfUp(Decimal::div($baseAmount, $rate->base_units_per_unit), $decimals);

        return [$converted, PriceMode::from($baseMode), 'converted'];
    }

    private function toGrossForComparison(string $amount, PriceMode $mode, string $percent, int $decimals): string
    {
        if ($mode === PriceMode::Gross) {
            return Decimal::roundHalfUp($amount, $decimals);
        }

        $vat = Decimal::div(Decimal::mul($amount, $percent), '100');

        return Decimal::roundHalfUp(Decimal::add($amount, $vat), $decimals);
    }
}
