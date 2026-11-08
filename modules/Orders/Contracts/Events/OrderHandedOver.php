<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts\Events;

/** Dispatched after the transaction that recorded the hand-over of an order commits. */
final readonly class OrderHandedOver
{
    public function __construct(
        public string $orderId,
        public \DateTimeImmutable $handedOverAt,
    ) {}
}
