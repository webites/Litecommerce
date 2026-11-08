<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Litecommerce\Modules\Orders\Contracts\CustomerOrderSummaryData;

final readonly class CustomerOrderListPageData
{
    /** @param list<CustomerOrderSummaryData> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
