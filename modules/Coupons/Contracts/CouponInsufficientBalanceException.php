<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final class CouponInsufficientBalanceException extends \RuntimeException
{
    public function __construct(
        public readonly string $couponCode,
        public readonly string $requested,
        public readonly string $available,
    ) {
        parent::__construct("Coupon [{$couponCode}] has available balance {$available}, requested {$requested}.");
    }
}
