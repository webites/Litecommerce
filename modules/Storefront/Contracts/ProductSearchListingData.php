<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Litecommerce\Modules\Search\Contracts\ProductSearchCriteria;
use Litecommerce\Modules\Search\Contracts\SearchFilterData;

final readonly class ProductSearchListingData
{
    /** @param list<SearchFilterData> $filters */
    public function __construct(
        public ProductListingPageData $page,
        public ProductSearchCriteria $criteria,
        public array $filters,
    ) {}
}
