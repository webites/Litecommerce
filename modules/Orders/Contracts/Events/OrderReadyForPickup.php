<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts\Events;

/** Dispatched after the transaction that marked an order ready for pickup commits. */
final readonly class OrderReadyForPickup
{
    public function __construct(
        public string $orderId,
        public \DateTimeImmutable $readyAt,
        public ?\DateTimeImmutable $deadlineAt,
    ) {}
}
