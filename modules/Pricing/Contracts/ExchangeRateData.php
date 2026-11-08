<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

final readonly class ExchangeRateData
{
    public function __construct(
        public string $currencyCode,
        public string $baseUnitsPerUnit,
    ) {}
}
