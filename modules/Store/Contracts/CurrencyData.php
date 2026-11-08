<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

final readonly class CurrencyData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $symbol,
        public int $decimalPlaces,
        public bool $isBase,
    ) {}
}
