<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

/**
 * Reservation lifecycle per docs/knowledge-base/05-orders.md's "Rezerwacje
 * i współbieżność" and 04-pricing-coupons.md's "Kupony" section. Mirrors
 * Warehouse\Contracts\StockReservationManager's reserve/confirm/release
 * shape, with one deliberate difference: for coupons, confirm() *is* the
 * consumption (it writes the permanent ledger 'usage' entry), there is no
 * separate fulfill() step. That makes release() able to reverse either a
 * Pending reservation (nothing was ever consumed — a plain unhold) or a
 * Confirmed one (money was consumed — release() writes a compensating
 * ledger 'restore' entry), covering both halves of "Anulowanie
 * niezapłaconego zamówienia zwalnia rezerwacje. Dla COD/płatności na
 * miejscu tworzy kompensujący zwrot salda kuponu" from the same call site.
 */
interface CouponReservationManager
{
    /**
     * Reserves amount (base currency) against the code's available balance
     * (issued − used − currently reserved). Reconciles this code's expired
     * reservations first, in the same transaction, so a missing cron/
     * fallback task never blocks new use indefinitely.
     *
     * @throws UnknownCouponException when the code does not resolve
     * @throws CouponInsufficientBalanceException when available balance is below the requested amount
     */
    public function reserve(
        string $code,
        string $amount,
        string $referenceType,
        string $referenceId,
        ?\DateTimeImmutable $expiresAt,
    ): CouponReservationData;

    /**
     * Pending -> Confirmed: writes the permanent ledger 'usage' entry and
     * releases the reservation's hold on reserved_amount (the ledger now
     * carries the deduction instead). Idempotent when already confirmed.
     *
     * @throws InvalidCouponReservationStateException when already Released/Expired — per the
     *                                                documented race, an Expired reservation here
     *                                                means payment confirmation lost to expiry
     *                                                reconciliation; the caller should route this
     *                                                to a late-payment flow, not a generic failure
     */
    public function confirm(int $reservationId): void;

    /**
     * Cancels a reservation. Pending -> Released with no ledger effect
     * (nothing was ever consumed). Confirmed -> Released with a
     * compensating ledger 'restore' entry (the earlier 'usage' is reversed).
     * Idempotent when already released/expired.
     */
    public function release(int $reservationId): void;

    /**
     * An independent compensating credit against a code's balance, not
     * tied to any reservation — the return/refund path, where an already-
     * fulfilled order's coupon usage is partially or fully given back per
     * "ReturnLine.coupon_allocations" (04-pricing-coupons.md#snapshot-i-zwroty).
     * Applied even to an expired/blocked code — the value returns to its
     * history and balance but stays unusable for a *new* reservation.
     */
    public function restore(string $code, string $amount, string $referenceType, string $referenceId, string $reason): void;

    /**
     * Releases every pending reservation past its expires_at. Pass a code
     * to reconcile just that resource (as reserve() already does before
     * creating a new hold), or null for a global sweep meant to be driven
     * by a scheduled task or traffic-driven fallback. The optional limit
     * caps how many coupons one global sweep touches.
     */
    public function reconcileExpiredReservations(?string $code = null, ?int $limit = null): int;
}
