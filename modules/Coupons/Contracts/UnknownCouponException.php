<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final class UnknownCouponException extends \InvalidArgumentException
{
    public function __construct(public readonly string $couponCode)
    {
        parent::__construct("Unknown coupon code [{$couponCode}].");
    }
}
