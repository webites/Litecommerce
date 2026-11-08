<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

enum DeliveryKind: string
{
    case FlatRate = 'flat_rate';
    case Pickup = 'pickup';
}
