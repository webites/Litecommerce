<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

/**
 * Thrown by StockReservationManager::confirm()/fulfill() when the
 * reservation is not in a state that allows the requested transition.
 *
 * This is the race described in docs/knowledge-base/05-orders.md
 * ("Rezerwacje i współbieżność"): if expiry reconciliation wins against a
 * concurrent payment confirmation, the reservation is already Expired by
 * the time confirm()/fulfill() runs. The caller is expected to catch this,
 * inspect `currentState`, and route an Expired/Released reservation to a
 * late-payment/manual-review flow rather than treat it as a generic error.
 */
final class InvalidReservationStateException extends \RuntimeException
{
    public function __construct(
        public readonly int $reservationId,
        public readonly StockReservationState $currentState,
        public readonly string $attemptedTransition,
    ) {
        parent::__construct(
            "Reservation {$reservationId} cannot transition to '{$attemptedTransition}' from state '{$currentState->value}'.",
        );
    }
}
