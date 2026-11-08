<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * Facts about the physical hand-over of an order's delivery. Every method is
 * idempotent: repeating a transition already recorded changes nothing and
 * returns false.
 */
interface OrderFulfillmentManager
{
    /**
     * Marks a pickup order ready and emails the customer the pickup point,
     * instructions and deadline. The deadline only ever lapses an unpaid
     * pay-on-pickup order; for a prepaid order it is informational.
     *
     * @throws InvalidFulfillmentTransitionException
     */
    public function markReadyForPickup(string $orderId, string $actorId, ?\DateTimeImmutable $deadline): bool;

    /**
     * Records the hand-over and settles stock. An unpaid pay-on-pickup order
     * needs the payment reference, recorded atomically with the hand-over.
     *
     * @throws InvalidFulfillmentTransitionException
     */
    public function confirmHandover(string $orderId, string $actorId, ?string $paymentReference, ?\DateTimeImmutable $paidAt = null): bool;

    /** Cancels an uncollected unpaid pay-on-pickup order and releases its stock and coupons. */
    public function expireUncollected(string $orderId, \DateTimeImmutable $now): bool;

    /** @return int number of orders expired */
    public function expireDue(\DateTimeImmutable $now, int $limit): int;

    /**
     * Emails each waiting pickup customer one reminder before the deadline.
     *
     * @return int number of reminders sent
     */
    public function remindDue(\DateTimeImmutable $now, int $limit): int;
}
