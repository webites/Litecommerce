<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

final class InvalidCouponReservationStateException extends \RuntimeException
{
    public function __construct(
        public readonly int $reservationId,
        public readonly CouponReservationState $currentState,
        public readonly string $attemptedTransition,
    ) {
        parent::__construct("Coupon reservation #{$reservationId} is in state [{$currentState->value}], cannot perform [{$attemptedTransition}].");
    }
}
