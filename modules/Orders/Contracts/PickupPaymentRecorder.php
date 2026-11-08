<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/**
 * Port implemented by Payments: records the money received at the counter
 * so that "ready for pickup" can be handed over in the same transaction.
 */
interface PickupPaymentRecorder
{
    public function recordReceivedOnPickup(string $orderId, string $reference, \DateTimeImmutable $paidAt, string $actorId): void;
}
