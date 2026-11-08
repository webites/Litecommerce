<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/**
 * Pricing/Warehouse enrichment for one of ProductViewData's variants — same
 * composition rule as ProductListingItemData.
 */
final readonly class ProductVariantOfferData
{
    /**
     * @param  array<string, string>  $optionValues  copied from VariantSummaryData, so the theme's variant picker can resolve a pick to one offer without a second lookup
     * @param  string  $optionSignature  precomputed data-storefront-show-if condition for this exact offer (e.g. "kolor:czerwony,rozmiar:m"), built from EVERY axis the product currently offers — not just the ones this offer happens to have — so several independently-rendered partials (price/add-to-cart, SKU, ...) can each render their own per-offer block keyed by the identical string without recomputing or duplicating the "missing axis" handling themselves. Empty for a product with no option axes.
     */
    public function __construct(
        public int $variantId,
        public string $sku,
        public string $gtin,
        public string $mpn,
        public string $displayName,
        public bool $enabled,
        public ?string $unitAmount,
        public ?string $regularUnitAmount,
        public ?string $promotionalUnitAmount,
        public bool $promotionalActive,
        public bool $available,
        public array $optionValues,
        public string $optionSignature,
        public ?string $shortDescription = null,
        public ?string $descriptionHtml = null,
    ) {}
}
