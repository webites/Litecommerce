<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Litecommerce\Modules\Cart\Contracts\AdjustmentContext;
use Litecommerce\Modules\Cart\Contracts\AdjustmentData;
use Litecommerce\Modules\Cart\Contracts\AdjustmentLineData;
use Litecommerce\Modules\Cart\Contracts\PricingAdjustmentRegistry;
use Litecommerce\Modules\Cart\Domain\AdjustmentAllocator;
use Litecommerce\Modules\Cart\Domain\Decimal;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * Runs the registered PricingAdjustment plugins over a priced checkout, then
 * splits every adjustment across the product lines in proportion to their
 * gross value, so a later partial return can refund its exact share.
 */
final class ApplyPricingAdjustments
{
    public function __construct(
        private readonly PricingAdjustmentRegistry $registry,
        private readonly CurrencyRepository $currencies,
    ) {}

    public function apply(PricedCart $priced, ?int $customerAccountId, string $locale): PricedCart
    {
        $lineIndexes = [];
        foreach ($priced->lines as $index => $line) {
            if ($line->available && $line->lineGrossAmount !== null) {
                $lineIndexes[] = $index;
            }
        }

        if ($this->registry->all() === [] || $lineIndexes === []) {
            return $priced;
        }

        $currency = $this->currencies->find($priced->currencyCode);
        $decimals = $currency === null ? 2 : $currency->decimalPlaces;

        $context = new AdjustmentContext(
            $priced->currencyCode, $locale, $customerAccountId,
            array_map(fn (int $i): AdjustmentLineData => new AdjustmentLineData(
                $priced->lines[$i]->variantId, (string) $priced->lines[$i]->productType, $priced->lines[$i]->quantity, (string) $priced->lines[$i]->lineGrossAmount,
            ), $lineIndexes),
            $priced->totalGrossAmount,
        );

        $weights = array_map(fn (int $i): string => (string) $priced->lines[$i]->lineGrossAmount, $lineIndexes);
        $adjustments = [];
        $allocations = array_fill_keys($lineIndexes, []);

        foreach ($this->registry->all() as $plugin) {
            foreach ($plugin->adjust($context) as $adjustment) {
                $this->assertValid($adjustment, $decimals, $adjustments);
                if (Decimal::compare($adjustment->grossAmount, '0') === 0 && Decimal::compare($adjustment->vatAmount, '0') === 0) {
                    continue;
                }

                $adjustments[$adjustment->code] = $this->present($adjustment);
                $grossShares = AdjustmentAllocator::split($adjustment->grossAmount, $weights, $decimals);
                $vatShares = AdjustmentAllocator::split($adjustment->vatAmount, $weights, $decimals);
                foreach ($lineIndexes as $position => $index) {
                    $allocations[$index][] = [
                        'code' => $adjustment->code, 'label' => $adjustment->label,
                        'net_amount' => Decimal::roundHalfUp(Decimal::sub($grossShares[$position], $vatShares[$position]), $decimals),
                        'vat_amount' => $vatShares[$position], 'gross_amount' => $grossShares[$position],
                    ];
                }
            }
        }

        if ($adjustments === []) {
            return $priced;
        }

        $result = new PricedCart(
            $priced->currencyCode,
            array_map(fn (int $i): PricedLine => isset($allocations[$i]) ? $priced->lines[$i]->withAdjustmentAllocations($allocations[$i]) : $priced->lines[$i], array_keys($priced->lines)),
            $priced->totalNetAmount, $priced->totalVatAmount, $priced->totalGrossAmount, $priced->allAvailable,
            $priced->delivery, $priced->couponOutcomes, array_values($adjustments),
        );

        if (Decimal::compare($result->grandTotalGrossAmount(), '0') < 0) {
            throw new \LogicException('Pricing adjustments would make the order total negative.');
        }

        return $result;
    }

    /** @param  array<string, mixed>  $known */
    private function assertValid(AdjustmentData $adjustment, int $decimals, array $known): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{1,63}$/', $adjustment->code)) {
            throw new \InvalidArgumentException("Invalid pricing adjustment code [{$adjustment->code}].");
        }
        if (isset($known[$adjustment->code])) {
            throw new \InvalidArgumentException("Pricing adjustment [{$adjustment->code}] was returned twice.");
        }
        foreach ([$adjustment->netAmount, $adjustment->vatAmount, $adjustment->grossAmount] as $amount) {
            if (! preg_match('/^-?\d+(\.\d+)?$/', $amount) || Decimal::compare(Decimal::roundHalfUp($amount, $decimals), $amount) !== 0) {
                throw new \InvalidArgumentException("Pricing adjustment [{$adjustment->code}] has an invalid amount [{$amount}].");
            }
        }
        if (Decimal::compare(Decimal::add($adjustment->netAmount, $adjustment->vatAmount), $adjustment->grossAmount) !== 0) {
            throw new \InvalidArgumentException("Pricing adjustment [{$adjustment->code}] net plus VAT does not equal gross.");
        }
    }

    /** @return array{code:string,label:string,net_amount:string,vat_amount:string,gross_amount:string} */
    private function present(AdjustmentData $adjustment): array
    {
        return [
            'code' => $adjustment->code, 'label' => $adjustment->label,
            'net_amount' => $adjustment->netAmount, 'vat_amount' => $adjustment->vatAmount, 'gross_amount' => $adjustment->grossAmount,
        ];
    }
}
