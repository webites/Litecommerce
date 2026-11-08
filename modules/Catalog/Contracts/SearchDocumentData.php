<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class SearchDocumentData
{
    /**
     * @param  list<int>  $categoryIds  Includes assigned categories and their ancestors.
     * @param  list<SearchDocumentAttributeData>  $attributes  Effective values for this exact variant.
     */
    public function __construct(
        public int $productId,
        public int $variantId,
        public string $locale,
        public string $slug,
        public string $productType,
        public string $productName,
        public string $variantName,
        public string $sku,
        public string $shortDescription,
        public string $description,
        public string $variantPath,
        public string $categoryText,
        public array $categoryIds,
        public array $attributes,
    ) {}
}
