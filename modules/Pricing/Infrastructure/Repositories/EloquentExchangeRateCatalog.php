<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\ExchangeRateCatalog;
use Litecommerce\Modules\Pricing\Contracts\ExchangeRateData;
use Litecommerce\Modules\Pricing\Infrastructure\Models\ExchangeRate;

final class EloquentExchangeRateCatalog implements ExchangeRateCatalog
{
    public function find(string $currencyCode): ?ExchangeRateData
    {
        $rate = ExchangeRate::query()->where('currency_code', $currencyCode)->first();

        return $rate === null ? null : new ExchangeRateData($rate->currency_code, $rate->base_units_per_unit);
    }
}
