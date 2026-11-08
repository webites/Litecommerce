<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

final readonly class VariantPriceData
{
    /**
     * @param  list<VariantPriceOverrideData>  $overrides
     */
    public function __construct(
        public int $variantId,
        public string $regularAmount,
        public PriceMode $regularMode,
        public ?string $promotionalAmount,
        public ?PriceMode $promotionalMode,
        public array $overrides,
    ) {}
}
