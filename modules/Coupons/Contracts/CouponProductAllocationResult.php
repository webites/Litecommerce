<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final readonly class CouponProductAllocationResult
{
    /**
     * @param  list<CouponCodeAllocationOutcome>  $codes
     * @param  array<int|string, list<array{code:string,amount:string,base_amount:string}>>  $lineAllocations  CouponLineContext::$key => per-code
     *                                                                                                         discount portions, each already at the cart currency's precision
     *                                                                                                         (amount) and the shop's base-currency ledger precision (base_amount)
     */
    public function __construct(
        public array $codes,
        public array $lineAllocations,
        public string $totalDiscount,
    ) {}
}
