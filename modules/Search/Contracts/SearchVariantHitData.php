<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

final readonly class SearchVariantHitData
{
    public function __construct(
        public int $variantId,
        public string $name,
        public string $sku,
        public string $path,
        public ?string $unitAmount,
        public ?string $regularUnitAmount,
        public bool $promotionalActive,
        public bool $available,
    ) {}
}
