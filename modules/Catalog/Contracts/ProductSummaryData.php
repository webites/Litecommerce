<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/** One row of a public product listing — price/availability are NOT here, Storefront enriches this with Pricing/Warehouse per its own composition rule (see "Kontrakty widoków", 09-presentation.md#projekt-techniczny-p1-013). */
final readonly class ProductSummaryData
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $type,
        public string $name,
        public ?string $shortDescription,
        public ?string $imageUrl,
        public int $defaultVariantId,
        public string $variantSeoMode,
        public ?string $imageSrcset = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
    ) {}
}
