<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

enum CouponReservationState: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Released = 'released';
    case Expired = 'expired';
}
