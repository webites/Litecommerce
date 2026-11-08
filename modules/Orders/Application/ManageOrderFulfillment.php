<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\OrderFulfillmentManager;

final class ManageOrderFulfillment implements OrderFulfillmentManager
{
    public function __construct(
        private readonly MarkReadyForPickup $ready,
        private readonly ConfirmHandover $handover,
        private readonly ExpireUncollected $expire,
        private readonly RemindPickupDeadline $reminders,
    ) {}

    public function markReadyForPickup(string $orderId, string $actorId, ?\DateTimeImmutable $deadline): bool
    {
        return $this->ready->execute($orderId, $actorId, $deadline);
    }

    public function confirmHandover(string $orderId, string $actorId, ?string $paymentReference, ?\DateTimeImmutable $paidAt = null): bool
    {
        return $this->handover->execute($orderId, $actorId, $paymentReference, $paidAt);
    }

    public function expireUncollected(string $orderId, \DateTimeImmutable $now): bool
    {
        return $this->expire->execute($orderId, $now);
    }

    public function expireDue(\DateTimeImmutable $now, int $limit): int
    {
        $expired = 0;

        foreach ($this->expire->dueOrderIds($now, $limit) as $orderId) {
            try {
                $expired += $this->expire->execute($orderId, $now) ? 1 : 0;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $expired;
    }

    public function remindDue(\DateTimeImmutable $now, int $limit): int
    {
        $sent = 0;

        foreach ($this->reminders->dueOrderIds($now, $limit) as $orderId) {
            try {
                $sent += $this->reminders->execute($orderId, $now) ? 1 : 0;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }
}
