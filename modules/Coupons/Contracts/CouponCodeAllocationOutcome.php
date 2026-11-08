<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

/**
 * Per-code result of Contracts\CouponPricer::allocateToProducts(). Only
 * Applied ever carries a non-zero allocatedAmount; every other status
 * means the code contributed nothing this time (shown to the shopper as
 * "kod niedostępny", never with the merchant-facing reason).
 */
final readonly class CouponCodeAllocationOutcome
{
    /**
     * @param  string  $allocatedAmount  In the cart/order's own currency — feeds the
     *                                   net/VAT-from-gross recompute and the customer-facing snapshot.
     * @param  string  $allocatedBaseAmount  The same allocation converted to the shop's base
     *                                       currency, at the rate read for this computation — the only
     *                                       amount Coupons\Contracts\CouponReservationManager ever
     *                                       accepts, since balances live in base currency
     *                                       ("Kwota i saldo w bazowej walucie", 04-pricing-coupons.md).
     */
    public function __construct(
        public string $code,
        public CouponAttachmentStatus $status,
        public string $allocatedAmount,
        public string $allocatedBaseAmount,
        public string $remainingForDelivery,
        public string $remainingForDeliveryBaseAmount,
        public bool $allowsDelivery,
    ) {}
}
