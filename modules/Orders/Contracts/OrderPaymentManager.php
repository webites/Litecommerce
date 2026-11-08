<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

interface OrderPaymentManager
{
    public function confirm(string $orderId, \DateTimeImmutable $paidAt, string $attemptId): string;

    public function cancelUnpaid(string $orderId, string $reason, ?string $actorId = null): void;

    public function markForPaymentReview(string $orderId, string $reason): void;

    public function extendDeadlineOnce(string $orderId, \DateTimeImmutable $until): bool;
}
