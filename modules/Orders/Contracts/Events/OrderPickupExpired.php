<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts\Events;

/** Dispatched after an uncollected order was cancelled and its stock and coupons released. */
final readonly class OrderPickupExpired
{
    public function __construct(
        public string $orderId,
        public \DateTimeImmutable $expiredAt,
    ) {}
}
