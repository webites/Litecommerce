<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure;

use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodSource;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateCatalog;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/** Supplies the "core" provider: every row of the Delivery settings tab. */
final class ConfiguredShippingMethodSource implements ShippingMethodSource
{
    private const KEY_PATTERN = '/^'.ConfiguredShippingMethod::PROVIDER.'\.([1-9][0-9]*)$/';

    public function __construct(
        private readonly CurrencyRepository $currencies,
        private readonly ExchangeRateCatalog $exchangeRates,
        private readonly ContinentResolver $continents,
    ) {}

    public function methods(): array
    {
        return DeliveryMethod::query()
            ->with(['currencyOverrides', 'regionRules.currencyOverrides', 'pickupPoints'])
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (DeliveryMethod $row): ShippingMethod => $this->adapt($row))
            ->all();
    }

    public function find(string $methodId): ?ShippingMethod
    {
        if (preg_match(self::KEY_PATTERN, $methodId, $match) !== 1) {
            return null;
        }

        $row = DeliveryMethod::query()->find((int) $match[1]);

        return $row === null ? null : $this->adapt($row);
    }

    private function adapt(DeliveryMethod $row): ShippingMethod
    {
        return new ConfiguredShippingMethod($row, $this->currencies, $this->exchangeRates, $this->continents);
    }
}
