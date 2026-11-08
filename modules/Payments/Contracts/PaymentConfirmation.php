<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentConfirmation
{
    public function confirmManual(string $orderId, string $reference, \DateTimeImmutable $paidAt, string $actorId): PaymentAttemptData;
}
