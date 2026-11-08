<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Contracts;

/**
 * Read side consumed by Orders\Presentation\Controllers\OrderController
 * (a "Zwroty" summary on the order detail page) and by Returns' own
 * panel — the same pattern as Payments\Contracts\PaymentAttemptCatalog::forOrder().
 */
interface ReturnCatalog
{
    /** @return list<ReturnRequestData> */
    public function findForOrder(string $orderId): array;

    public function find(string $returnRequestId): ?ReturnRequestData;

    /** @return list<ReturnReasonData> */
    public function activeReasons(): array;

    /** @return list<ReturnReasonData> including inactive ones, for the panel's own management screen */
    public function allReasons(): array;
}
