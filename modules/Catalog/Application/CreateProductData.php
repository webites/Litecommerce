<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

final readonly class CreateProductData
{
    public function __construct(
        public string $locale,
        public string $name,
        public ?string $shortDescription,
        public ?string $descriptionHtml,
        public string $type,
        public string $status,
        /** Null means "no override — inherit catalog_seo_settings.default_variant_seo_mode" (see EloquentProductCatalog::effectiveVariantSeoMode()). */
        public ?string $variantSeoMode,
        public bool $featured,
        public string $unitCode,
        public string $minimumQuantity,
        public ?string $maximumQuantity,
        public string $quantityStep,
        public ?string $sku,
        public ?string $gtin,
        public ?string $mpn,
        public ?int $brandId,
        public ?int $primaryCategoryId,
        /** @var list<int> */
        public array $categoryIds,
    ) {}
}
