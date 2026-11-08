<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

/** Where the physical hand-over of an order's delivery stands; independent of the order status. */
enum DeliveryFulfillmentState: string
{
    case Pending = 'pending';
    case ReadyForPickup = 'ready_for_pickup';
    case HandedOver = 'handed_over';
    case PickupExpired = 'pickup_expired';
}
