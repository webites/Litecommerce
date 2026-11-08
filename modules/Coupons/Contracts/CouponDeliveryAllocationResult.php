<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final readonly class CouponDeliveryAllocationResult
{
    /** @param  list<CouponDeliveryAllocationEntry>  $entries */
    public function __construct(
        public array $entries,
        public string $totalDiscount,
    ) {}
}
