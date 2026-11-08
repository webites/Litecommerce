<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

/**
 * Pure, read-only pricing computation — steps 3 and 6 of the checkout
 * pipeline in docs/knowledge-base/05-orders.md. Never mutates PIN attempts,
 * balances or reservations; safe to call on every price recompute. Codes
 * are applied in the given order — "w kolejności zastosowania zapisanej w
 * koszyku" — each one allocated fully (up to its own balance) before the
 * next gets a turn, not split proportionally between codes.
 */
interface CouponPricer
{
    /**
     * @param  list<string>  $codes  Attached codes, in cart attachment order.
     * @param  list<CouponLineContext>  $lines  In a stable order (see CouponLineContext).
     */
    public function allocateToProducts(
        array $codes,
        string $currencyCode,
        ?int $customerAccountId,
        array $lines,
    ): CouponProductAllocationResult;

    /**
     * Step 6 — spends each code's product-allocation remainder (only for
     * codes configured to allow it) against the delivery amount, per
     * "Priorytet kuponu: najpierw produkty" (04-pricing-coupons.md).
     *
     * @param  list<CouponCodeAllocationOutcome>  $productOutcomes  The result of allocateToProducts() for the same codes.
     */
    public function allocateToDelivery(array $productOutcomes, string $deliveryGrossAmount, string $currencyCode): CouponDeliveryAllocationResult;
}
