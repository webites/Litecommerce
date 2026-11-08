<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

/**
 * Reservation lifecycle per docs/knowledge-base/06-fulfillment.md and the
 * "Rezerwacje i współbieżność" section of 05-orders.md. There is no Cart
 * or Orders module yet — reference_type/reference_id let a future caller
 * (an order line, a manual hold, ...) tag a reservation without Warehouse
 * depending on that module's contracts. This mirrors how Pricing's
 * PriceQuoter was built ready for a Cart/Checkout that didn't exist yet.
 */
interface StockReservationManager
{
    /**
     * Reserves quantity against the variant's stock item, creating the
     * item (0 on hand, backorder disabled) on first use. Reconciles this
     * variant's expired reservations first, in the same transaction, so a
     * missing cron/fallback task never blocks sales indefinitely.
     *
     * @throws InsufficientStockException when available stock (on hand
     *                                    minus already-reserved) is below
     *                                    the requested quantity and the
     *                                    variant does not allow backorder
     */
    public function reserve(
        int $variantId,
        string $quantity,
        string $referenceType,
        string $referenceId,
        ?\DateTimeImmutable $expiresAt,
    ): StockReservationData;

    /**
     * Transitions a pending reservation to confirmed and indefinite (no
     * longer subject to expiry) — the "finalne potwierdzenie zapłaty"
     * moment. Idempotent when already confirmed.
     *
     * @throws InvalidReservationStateException when the reservation is
     *                                          already Expired/Released/Fulfilled —
     *                                          per the documented race, an Expired
     *                                          reservation here means payment confirmation
     *                                          lost to expiry reconciliation; the caller
     *                                          should route this to a late-payment flow,
     *                                          not treat it as a generic failure
     */
    public function confirm(int $reservationId): void;

    /**
     * Cancels a pending/confirmed reservation without touching physical
     * stock. Idempotent when already released/expired/fulfilled.
     */
    public function release(int $reservationId): void;

    /**
     * Atomically settles the reservation and decreases on-hand stock —
     * the "wysłanie/odbiór" moment. Idempotent when already fulfilled.
     *
     * @throws InvalidReservationStateException when the reservation is
     *                                          already Expired/Released — the caller
     *                                          must not fall back to decreasing stock
     *                                          anyway, since it may already be resold;
     *                                          route to a late-payment/manual-review flow
     */
    public function fulfill(int $reservationId, ?string $actorId = null): void;

    /**
     * Releases every pending reservation past its expires_at. Pass a
     * variant id to reconcile just that resource (as reserve() already
     * does before creating a new hold), or null for a global sweep meant
     * to be driven by a scheduled task or traffic-driven fallback. The
     * optional limit caps how many stock items one global sweep touches.
     */
    public function reconcileExpiredReservations(?int $variantId = null, ?int $limit = null): int;
}
