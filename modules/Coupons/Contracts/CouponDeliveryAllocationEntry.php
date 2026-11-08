<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final readonly class CouponDeliveryAllocationEntry
{
    public function __construct(
        public string $code,
        public string $amount,
        public string $baseAmount,
    ) {}
}
