<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Litecommerce\Modules\Catalog\Contracts\ProductOptionAxisData;
use Litecommerce\Modules\Catalog\Contracts\ProductViewData;

// Passed to every ProductTabRegistry render() call, so a plugin tab can build the same
// per-offer, data-storefront-show-if-keyed markup the built-in Specyfikacja tab uses,
// reacting to the visitor's variant pick on both the "blade" and "blade-react" engines.
final readonly class ProductTabContext
{
    /**
     * @param  list<ProductOptionAxisData>  $axes
     * @param  list<ProductVariantOfferData>  $offers
     */
    public function __construct(
        public ProductViewData $product,
        public array $axes,
        public array $offers,
    ) {}
}
