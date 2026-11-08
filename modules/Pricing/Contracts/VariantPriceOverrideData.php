<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

final readonly class VariantPriceOverrideData
{
    public function __construct(
        public string $currencyCode,
        public ?string $regularAmount,
        public ?PriceMode $regularMode,
        public ?string $promotionalAmount,
        public ?PriceMode $promotionalMode,
    ) {}
}
