<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/**
 * Full product page data OWNED by Catalog — name/description/media/
 * options/variant identities. Deliberately excludes price and stock
 * (Pricing/Warehouse own those); Storefront composes both onto this, per
 * "Kontrakty widoków" (09-presentation.md#projekt-techniczny-p1-013).
 * optionAxes + each variant's VariantSummaryData::$optionValues together
 * let a theme build a swatch/button picker that resolves to one concrete
 * variant, per 09-presentation.md#domyślny-motyw.
 */
final readonly class ProductViewData
{
    /**
     * @param  list<CategorySummaryData>  $categories
     * @param  list<string>  $imageUrls
     * @param  list<VariantSummaryData>  $variants
     * @param  list<ProductOptionAxisData>  $optionAxes  ordered by product_options.position; empty when the product has no variant-defining options
     * @param  list<ProductSummaryData>  $upsells
     * @param  list<ProductSummaryData>  $crossSells
     * @param  list<?string>  $imageSrcsets  positionally parallel to $imageUrls
     * @param  list<?int>  $imageWidths  positionally parallel to $imageUrls
     * @param  list<?int>  $imageHeights  positionally parallel to $imageUrls
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $type,
        public string $variantSeoMode,
        public string $name,
        public ?string $shortDescription,
        public ?string $descriptionHtml,
        public ?string $brandName,
        public array $categories,
        public array $imageUrls,
        public array $variants,
        public array $optionAxes,
        public array $upsells,
        public array $crossSells,
        public array $imageSrcsets = [],
        public array $imageWidths = [],
        public array $imageHeights = [],
    ) {}
}
