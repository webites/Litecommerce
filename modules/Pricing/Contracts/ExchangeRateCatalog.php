<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface ExchangeRateCatalog
{
    /**
     * Null means no manual rate is configured for this currency (or it is
     * the shop's base currency, which never has one).
     */
    public function find(string $currencyCode): ?ExchangeRateData;
}
