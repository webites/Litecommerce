<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/** One listing row — Catalog's ProductSummaryData enriched with the default variant's price/availability, since Catalog does not own Pricing/Warehouse data (see "Kontrakty widoków", docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013). */
final readonly class ProductListingItemData
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $shortDescription,
        public ?string $imageUrl,
        public ?string $unitAmount,
        public ?string $regularUnitAmount,
        public bool $promotionalActive,
        public string $currencyCode,
        public int $currencyDecimalPlaces,
        public bool $available,
        public int $defaultVariantId,
        public ?string $imageSrcset = null,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
        public ?string $url = null,
    ) {}
}
