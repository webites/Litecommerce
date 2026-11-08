<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class VariantData
{
    public function __construct(
        public int $id,
        public int $productId,
        public string $productType,
        public string $sku,
        public bool $enabled,
        public string $unitCode,
        public string $minimumQuantity,
        public ?string $maximumQuantity,
        public string $quantityStep,
    ) {}
}
