<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * Unlike OrderConfirmationCatalog, both methods here enforce ownership
 * themselves (filter by $customerAccountId at the query level) — the
 * caller (a customer-account storefront page) has no prior ownership
 * check of its own to rely on.
 */
interface CustomerOrderCatalog
{
    /** @return array{items: list<CustomerOrderSummaryData>, total: int} */
    public function paginateForCustomer(int $customerAccountId, int $perPage, int $page): array;

    public function findForCustomer(string $orderId, int $customerAccountId): ?OrderDetailData;
}
