<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

final readonly class ProductListingPageData
{
    /** @param list<ProductListingItemData> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
