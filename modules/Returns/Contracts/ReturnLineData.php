<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Contracts;

final readonly class ReturnLineData
{
    /** @param  list<array{code:string,amount:string,base_amount:string}>  $couponAllocations  base_amount feeds Coupons\Contracts\CouponReservationManager::restore() */
    /** @param  list<array{code:string,label:string,net_amount:string,vat_amount:string,gross_amount:string}>  $adjustmentAllocations */
    public function __construct(
        public int $id,
        public int $orderLineId,
        public string $quantity,
        public ?int $reasonId,
        public ?string $reasonComment,
        public string $status,
        public bool $restock,
        public ?string $restockedQuantity,
        public ?string $netAmount,
        public ?string $vatAmount,
        public ?string $grossAmount,
        public array $couponAllocations,
        public ?string $moneyRefundAmount,
        public ?string $decisionReason,
        public ?string $decidedBy,
        public ?\DateTimeImmutable $decidedAt,
        public array $adjustmentAllocations = [],
    ) {}
}
