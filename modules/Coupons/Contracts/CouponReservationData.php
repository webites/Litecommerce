<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final readonly class CouponReservationData
{
    public function __construct(
        public int $id,
        public string $couponCode,
        public string $amount,
        public CouponReservationState $state,
        public string $referenceType,
        public string $referenceId,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
