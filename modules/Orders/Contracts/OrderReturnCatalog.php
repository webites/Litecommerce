<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * Read side consumed by Returns\Application\* — the immutable order/line
 * snapshot that "jest źródłem faktury, refundu i korekty"
 * (docs/knowledge-base/04-pricing-coupons.md#snapshot-i-zwroty). Order
 * lines are write-once, so unlike OrderPaymentCatalog this needs no
 * locking concern on the caller's side.
 */
interface OrderReturnCatalog
{
    public function find(string $orderId): ?OrderReturnableData;
}
