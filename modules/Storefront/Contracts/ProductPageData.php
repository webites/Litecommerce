<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Litecommerce\Modules\Catalog\Contracts\ProductViewData;

final readonly class ProductPageData
{
    /**
     * @param  list<ProductVariantOfferData>  $variantOffers  same order as $product->variants
     * @param  list<ProductListingItemData>  $upsells  same shape/priced-and-availability as the listing page, so the theme can reuse one product-card partial for both
     * @param  list<ProductListingItemData>  $crossSells
     * @param  ?array<string, string>  $preselectedOptionValues  axis key => value key resolved from a variant deep-link URL; null on the base product URL, where nothing is preselected
     * @param  ?string  $preselectedOptionSignature  the already-resolved offer signature for server-rendering the right variant content on a deep-link; null on the base product URL
     */
    public function __construct(
        public ProductViewData $product,
        public array $variantOffers,
        public string $currencyCode,
        public int $currencyDecimalPlaces,
        public array $upsells,
        public array $crossSells,
        public ?array $preselectedOptionValues = null,
        public ?string $preselectedOptionSignature = null,
    ) {}
}
