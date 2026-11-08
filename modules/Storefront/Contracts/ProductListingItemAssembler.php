<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Litecommerce\Modules\Catalog\Contracts\ProductSummaryData;

/** Enriches Catalog's ProductSummaryData rows with Pricing/Warehouse data — the shared composition step behind every product listing (catalog browsing, upsells/cross-sells, the Cms product-grid block), so none of them duplicate price/stock lookups. */
interface ProductListingItemAssembler
{
    /**
     * @param  list<ProductSummaryData>  $items
     * @return list<ProductListingItemData>
     */
    public function assemble(array $items, string $currencyCode): array;
}
