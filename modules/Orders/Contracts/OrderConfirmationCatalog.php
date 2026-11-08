<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * Bare, trusted-caller read of a full order — same convention as
 * OrderPaymentCatalog/DigitalAccessCatalog::findForOrder(), neither of
 * which checks ownership either. Used by the storefront's checkout
 * confirmation page AFTER Cart\Application\CartResolver has already
 * verified the requester owns the checkout draft that produced this order
 * — see "Punktowe rozszerzenia istniejących kontraktów"
 * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013). A
 * customer's own "my orders" page must NOT use this — it has no prior
 * ownership check, so it uses CustomerOrderCatalog instead, which filters
 * by customer_account_id itself.
 */
interface OrderConfirmationCatalog
{
    public function find(string $orderId): ?OrderDetailData;
}
