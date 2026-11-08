<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

/**
 * Consumed directly by Orders\Application\PlaceOrder (a free/fully
 * coupon-covered order) and Orders\Application\ManageOrderPayment (the
 * 'confirmed' outcome of a paid order) in the same DB transaction that
 * settles payment — the same wiring Warehouse\Contracts\StockReservationManager
 * already has into those two classes. Orders passes scalars only, so
 * Digital never depends on Orders' models
 * (docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy: "Po potwierdzeniu
 * zapłaty ... dostęp niezależny od wysyłki części fizycznej").
 *
 * Deliberately NOT called for a late_payment/overpayment outcome — a late
 * payment requires a staff decision (05-orders.md), made later through
 * Orders\Contracts\OrderDigitalAccessGranting, which calls back into this
 * interface.
 */
interface DigitalAccessGrantor
{
    /**
     * Grants one DownloadGrant per active, purchasable file of the
     * variant, snapshotting each file's current version/limit/validity.
     * A digital order line is always quantity 1 (Catalog enforces this),
     * so there is no quantity parameter. Idempotent per
     * (referenceType, referenceId, file) — replaying a webhook or retried
     * confirmation never doubles the grant.
     *
     * @return list<DigitalGrantData> empty when the variant has no
     *                                purchasable file at grant time
     */
    public function grantForOrderLine(
        int $variantId,
        string $referenceType,
        string $referenceId,
        ?string $orderId,
        ?int $customerAccountId,
        string $email,
        \DateTimeImmutable $grantedAt,
    ): array;
}
