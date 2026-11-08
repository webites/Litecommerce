<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Application;

use Litecommerce\Modules\Delivery\Contracts\DeliveryKind;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryOptionQuote;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Delivery\Contracts\RestrictsSellableCountries;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodQuote;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Delivery\Domain\Decimal;
use Litecommerce\Modules\Delivery\Domain\MerchantShippingSettings;
use Litecommerce\Modules\Delivery\Infrastructure\ShippingMethodSettingsResolver;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PricingSettingsCatalog;
use Litecommerce\Modules\Pricing\Contracts\TaxQualificationKind;
use Litecommerce\Modules\Pricing\Contracts\TaxRateCatalog;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Throwable;

/**
 * Checkout-facing view of delivery: every method in the registry (the
 * configured "core" ones and plugin ones) that the merchant has enabled and
 * that currently quotes, split into net/VAT/gross by the merchant-assigned
 * tax rate under the shop's current price entry mode. A method that throws
 * is unavailable, never a purchasable option — see 06-fulfillment.md.
 */
final class RegistryDeliveryMethodCatalog implements DeliveryMethodCatalog
{
    public function __construct(
        private readonly ShippingMethodRegistry $registry,
        private readonly ShippingMethodSettingsResolver $settings,
        private readonly CurrencyRepository $currencies,
        private readonly TaxRateCatalog $taxRates,
        private readonly PricingSettingsCatalog $pricing,
    ) {}

    public function quoteAvailable(DeliveryQuoteContext $context, string $locale): array
    {
        $methods = $this->registry->methods();
        $settings = $this->settings->resolve($methods);
        $positioned = [];

        foreach ($methods as $index => $method) {
            $merchant = $settings[$method->descriptor()->id];
            $option = $this->option($method, $merchant, $context, $locale);

            if ($option !== null) {
                $positioned[] = [$merchant->position, $index, $option];
            }
        }

        usort($positioned, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $entry): DeliveryOptionQuote => $entry[2], $positioned);
    }

    public function quote(string $methodId, DeliveryQuoteContext $context, string $locale): ?DeliveryOptionQuote
    {
        if (! $this->registry->has($methodId)) {
            return null;
        }

        $method = $this->registry->get($methodId);

        return $this->option($method, $this->settings->resolve([$method])[$methodId], $context, $locale);
    }

    public function supportsCashOnDelivery(string $methodId): bool
    {
        $method = $this->methodOrNull($methodId);

        if ($method === null) {
            return false;
        }

        return $method->descriptor()->supportsCashOnDelivery && $this->settings->resolve([$method])[$methodId]->allowCashOnDelivery;
    }

    public function supportsPayOnPickup(string $methodId): bool
    {
        $method = $this->methodOrNull($methodId);

        if ($method === null) {
            return false;
        }

        $descriptor = $method->descriptor();

        return $descriptor->kind === DeliveryKind::Pickup
            && $descriptor->supportsPayOnPickup
            && $this->settings->resolve([$method])[$methodId]->allowPayOnPickup;
    }

    public function pickupDeadlineDays(string $methodId): ?int
    {
        $method = $this->methodOrNull($methodId);

        if ($method === null || $method->descriptor()->kind !== DeliveryKind::Pickup) {
            return null;
        }

        return $this->settings->resolve([$method])[$methodId]->pickupDeadlineDays;
    }

    public function sellableCountryCodes(): array
    {
        /** @var list<string> $universe */
        $universe = array_keys(config('delivery.continents', []));
        $methods = $this->registry->methods();
        $settings = $this->settings->resolve($methods);
        $sellable = [];

        foreach ($methods as $method) {
            $descriptor = $method->descriptor();

            if (! $settings[$descriptor->id]->active || $descriptor->kind === DeliveryKind::Pickup) {
                continue;
            }

            if (! $method instanceof RestrictsSellableCountries) {
                return $universe;
            }

            foreach ($method->sellableCountryCodes() as $code) {
                $sellable[$code] = true;
            }
        }

        return $sellable === [] ? $universe : array_keys($sellable);
    }

    private function methodOrNull(string $methodId): ?ShippingMethod
    {
        return $this->registry->has($methodId) ? $this->registry->get($methodId) : null;
    }

    private function option(ShippingMethod $method, MerchantShippingSettings $merchant, DeliveryQuoteContext $context, string $locale): ?DeliveryOptionQuote
    {
        if (! $merchant->active) {
            return null;
        }

        $quote = $this->safeQuote($method, $context, $locale);

        if ($quote === null) {
            return null;
        }

        $descriptor = $method->descriptor();
        $currency = $this->currencies->find($context->currencyCode);
        $decimals = $currency === null ? 2 : $currency->decimalPlaces;
        $amount = Decimal::roundHalfUp($quote->amount, $decimals);
        [$net, $vat, $gross] = $this->split($amount, $this->pricing->entryMode(), $this->taxPercentage($merchant->taxRateId), $decimals);

        return new DeliveryOptionQuote(
            methodId: $descriptor->id,
            providerId: $descriptor->providerId,
            kind: $descriptor->kind->value,
            name: $descriptor->name,
            netAmount: $net,
            vatAmount: $vat,
            grossAmount: $gross,
            freeThresholdApplied: $quote->freeThresholdApplied,
            supportsCashOnDelivery: $descriptor->supportsCashOnDelivery && $merchant->allowCashOnDelivery,
            supportsPayOnPickup: $descriptor->kind === DeliveryKind::Pickup && $descriptor->supportsPayOnPickup && $merchant->allowPayOnPickup,
            pickupPoints: $descriptor->kind === DeliveryKind::Pickup ? $quote->pickupPoints : [],
        );
    }

    private function safeQuote(ShippingMethod $method, DeliveryQuoteContext $context, string $locale): ?ShippingMethodQuote
    {
        try {
            if (! $method->available($context)) {
                return null;
            }

            $quote = $method->quote($context, $locale);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if ($quote === null) {
            return null;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $quote->amount) !== 1) {
            report(new \UnexpectedValueException("Shipping method [{$method->descriptor()->id}] returned an invalid amount."));

            return null;
        }

        return $quote;
    }

    private function taxPercentage(?int $taxRateId): string
    {
        $taxRate = $taxRateId === null ? null : $this->taxRates->find($taxRateId);

        if ($taxRate !== null && $taxRate->kind === TaxQualificationKind::Percentage && $taxRate->percentage !== null) {
            return $taxRate->percentage;
        }

        return '0';
    }

    /** @return array{0:string,1:string,2:string} [net, vat, gross] */
    private function split(string $amount, PriceMode $mode, string $percent, int $decimals): array
    {
        if ($mode === PriceMode::Net) {
            $net = $amount;
            $vat = Decimal::roundHalfUp(Decimal::div(Decimal::mul($net, $percent), '100'), $decimals);
            $gross = Decimal::roundHalfUp(Decimal::add($net, $vat), $decimals);
        } else {
            $gross = $amount;
            $vat = Decimal::roundHalfUp(Decimal::div(Decimal::mul($gross, $percent), Decimal::add('100', $percent)), $decimals);
            $net = Decimal::roundHalfUp(Decimal::sub($gross, $vat), $decimals);
        }

        return [$net, $vat, $gross];
    }
}
