<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure;

use Illuminate\Support\Collection;
use Litecommerce\Modules\Delivery\Contracts\DeliveryKind;
use Litecommerce\Modules\Delivery\Contracts\DeliveryPickupPointData;
use Litecommerce\Modules\Delivery\Contracts\DeliveryQuoteContext;
use Litecommerce\Modules\Delivery\Contracts\RestrictsSellableCountries;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodDescriptor;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodQuote;
use Litecommerce\Modules\Delivery\Domain\Decimal;
use Litecommerce\Modules\Delivery\Domain\HasOwnMerchantSettings;
use Litecommerce\Modules\Delivery\Domain\MerchantShippingSettings;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethodCurrencyOverride;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryPickupPoint;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryRegionRule;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateCatalog;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateData;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * Exposes one configuration managed in the Delivery settings tab as a
 * ShippingMethod with the provider id "core". Prices are priced from region
 * rules (flat rate) or the pickup price, with currency overrides, exchange
 * rates and the free-shipping threshold, per 06-fulfillment.md.
 */
final class ConfiguredShippingMethod implements HasOwnMerchantSettings, RestrictsSellableCountries, ShippingMethod
{
    public const PROVIDER = 'core';

    public function __construct(
        private readonly DeliveryMethod $row,
        private readonly CurrencyRepository $currencies,
        private readonly ExchangeRateCatalog $exchangeRates,
        private readonly ContinentResolver $continents,
    ) {}

    public static function keyFor(int $deliveryMethodId): string
    {
        return self::PROVIDER.'.'.$deliveryMethodId;
    }

    public function descriptor(): ShippingMethodDescriptor
    {
        return new ShippingMethodDescriptor(
            self::keyFor((int) $this->row->getKey()),
            self::PROVIDER,
            $this->row->name,
            $this->row->supports_cash_on_delivery,
            $this->row->supports_pay_on_pickup,
            DeliveryKind::from($this->row->kind),
        );
    }

    public function merchantSettings(): MerchantShippingSettings
    {
        return new MerchantShippingSettings(
            $this->row->active,
            $this->row->position,
            $this->row->tax_rate_id,
            $this->row->supports_cash_on_delivery,
            $this->row->supports_pay_on_pickup,
            $this->row->pickup_deadline_days,
        );
    }

    public function available(DeliveryQuoteContext $context): bool
    {
        return $this->price($context) !== null;
    }

    public function quote(DeliveryQuoteContext $context, string $locale): ?ShippingMethodQuote
    {
        $price = $this->price($context);

        if ($price === null) {
            return null;
        }

        return new ShippingMethodQuote($price['amount'], $price['free'], $this->pickupPoints($locale));
    }

    public function sellableCountryCodes(): array
    {
        /** @var list<string> $universe */
        $universe = array_keys(config('delivery.continents', []));
        $this->row->loadMissing('regionRules');
        $excluded = [];
        $countryRated = [];
        $continentsRated = [];

        foreach ($this->row->regionRules as $rule) {
            match ($rule->rule_type) {
                'exclude' => $excluded[$rule->country_code] = true,
                'country_rate' => $countryRated[$rule->country_code] = true,
                'continent_rate' => $continentsRated[$rule->continent_code] = true,
                default => null,
            };
        }

        $sellable = [];

        foreach ($universe as $code) {
            if (isset($excluded[$code])) {
                continue;
            }

            $continent = $this->continents->continentFor($code);

            if (isset($countryRated[$code]) || ($continent !== null && isset($continentsRated[$continent]))) {
                $sellable[] = $code;
            }
        }

        return $sellable;
    }

    /** @return array{amount: string, free: bool}|null */
    private function price(DeliveryQuoteContext $context): ?array
    {
        $baseCurrency = $this->currencies->base();

        if ($baseCurrency === null) {
            return null;
        }

        $this->row->loadMissing(['currencyOverrides', 'regionRules.currencyOverrides', 'pickupPoints']);
        $isBaseCurrency = $context->currencyCode === $baseCurrency->code;
        $target = $this->currencies->find($context->currencyCode);
        $decimals = $target === null ? 2 : $target->decimalPlaces;
        $rate = $isBaseCurrency ? null : $this->exchangeRates->find($context->currencyCode);
        /** @var DeliveryMethodCurrencyOverride|null $methodOverride */
        $methodOverride = $this->row->currencyOverrides->firstWhere('currency_code', $context->currencyCode);

        if (DeliveryKind::from($this->row->kind) === DeliveryKind::Pickup) {
            if ($this->activePickupPoints()->isEmpty()) {
                return null;
            }

            $amount = $this->resolveAmount($this->row->price_amount ?? '0', $methodOverride?->price_amount, $isBaseCurrency, $rate, $decimals);
        } else {
            if ($context->countryCode === null) {
                return null;
            }

            $rule = $this->matchRegionRule($this->row->regionRules, strtoupper($context->countryCode));

            if ($rule === null) {
                return null;
            }

            $ruleOverride = $rule->currencyOverrides->firstWhere('currency_code', $context->currencyCode);
            $amount = $this->resolveAmount($rule->price_amount, $ruleOverride?->price_amount, $isBaseCurrency, $rate, $decimals);
        }

        if ($amount === null) {
            return null;
        }

        $threshold = $this->resolveAmount($this->row->free_threshold_amount, $methodOverride?->free_threshold_amount, $isBaseCurrency, $rate, $decimals);
        $free = $threshold !== null && Decimal::compare($context->goodsAmountForThreshold, $threshold) >= 0;

        return ['amount' => $free ? Decimal::roundHalfUp('0', $decimals) : $amount, 'free' => $free];
    }

    /** @return Collection<int, DeliveryPickupPoint> */
    private function activePickupPoints(): Collection
    {
        /** @var Collection<int, DeliveryPickupPoint> $points */
        $points = $this->row->pickupPoints->where('active', true)->sortBy('position')->values();

        return $points;
    }

    /** @return list<DeliveryPickupPointData> */
    private function pickupPoints(string $locale): array
    {
        if (DeliveryKind::from($this->row->kind) !== DeliveryKind::Pickup) {
            return [];
        }

        return $this->activePickupPoints()
            ->map(fn (DeliveryPickupPoint $point): DeliveryPickupPointData => $this->toPickupPointData($point, $locale))
            ->all();
    }

    /** @param Collection<int, DeliveryRegionRule> $rules */
    private function matchRegionRule(Collection $rules, string $countryCode): ?DeliveryRegionRule
    {
        $excluded = $rules->first(static fn (DeliveryRegionRule $rule): bool => $rule->rule_type === 'exclude' && $rule->country_code === $countryCode);

        if ($excluded !== null) {
            return null;
        }

        $countryRate = $rules->first(static fn (DeliveryRegionRule $rule): bool => $rule->rule_type === 'country_rate' && $rule->country_code === $countryCode);

        if ($countryRate !== null) {
            return $countryRate;
        }

        $continent = $this->continents->continentFor($countryCode);

        if ($continent === null) {
            return null;
        }

        return $rules->first(static fn (DeliveryRegionRule $rule): bool => $rule->rule_type === 'continent_rate' && $rule->continent_code === $continent);
    }

    private function resolveAmount(?string $baseAmount, ?string $overrideAmount, bool $isBaseCurrency, ?ExchangeRateData $rate, int $decimals): ?string
    {
        if ($overrideAmount !== null) {
            return Decimal::roundHalfUp($overrideAmount, $decimals);
        }

        if ($baseAmount === null) {
            return null;
        }

        if ($isBaseCurrency) {
            return Decimal::roundHalfUp($baseAmount, $decimals);
        }

        if ($rate === null) {
            return null;
        }

        return Decimal::roundHalfUp(Decimal::div($baseAmount, $rate->baseUnitsPerUnit), $decimals);
    }

    private function toPickupPointData(DeliveryPickupPoint $point, string $locale): DeliveryPickupPointData
    {
        $instructions = $point->instructions ?? [];

        return new DeliveryPickupPointData(
            id: (int) $point->getKey(),
            name: $point->name,
            countryCode: $point->country_code,
            postalCode: $point->postal_code,
            city: $point->city,
            region: $point->region,
            addressLine1: $point->address_line_1,
            addressLine2: $point->address_line_2,
            instructions: $instructions[$locale] ?? ($instructions[array_key_first($instructions)] ?? null),
        );
    }
}
