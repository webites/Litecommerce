<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class OrderLineDetailData
{
    /**
     * @param  list<array{code:string,amount:string,base_amount:string}>  $couponAllocations  amount is in the
     *                                                                                        order's own currency (feeds refund math); base_amount is the shop's
     *                                                                                        base currency (the only unit Coupons\Contracts\CouponReservationManager accepts)
     * @param  list<array{code:string,label:string,net_amount:string,vat_amount:string,gross_amount:string}>  $adjustmentAllocations  this line's share of each
     *                                                                                                                                order-level pricing adjustment; refunds follow the returned share of the line
     */
    public function __construct(
        public int $id,
        public int $variantId,
        public string $productType,
        public string $sku,
        public string $displayName,
        public string $quantity,
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
        public array $couponAllocations,
        public array $adjustmentAllocations = [],
    ) {}
}
