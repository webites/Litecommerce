<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Litecommerce\Modules\Orders\Contracts\PickupPaymentRecorder;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;

/** Records the money taken at the pickup counter through the same manual confirmation staff use elsewhere. */
final class RecordPickupPayment implements PickupPaymentRecorder
{
    public function __construct(private readonly PaymentConfirmation $confirmation) {}

    public function recordReceivedOnPickup(string $orderId, string $reference, \DateTimeImmutable $paidAt, string $actorId): void
    {
        $this->confirmation->confirmManual($orderId, $reference, $paidAt, $actorId);
    }
}
