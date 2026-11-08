<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Litecommerce\Modules\Coupons\Contracts\CouponDeliveryAllocationEntry;
use Litecommerce\Modules\Delivery\Contracts\DeliveryOptionQuote;

/**
 * The delivery slice of pipeline steps 4-6 in docs/knowledge-base/05-orders.md
 * ("Pipeline wyceny"). Absent entirely (null on PricedCart) when the cart
 * has no physical lines — "Wyłącznie cyfrowy nie wymaga dostawy."
 */
final readonly class PricedDelivery
{
    /**
     * @param  list<DeliveryOptionQuote>  $options
     * @param  string  $couponDiscountAmount  Step 6's coupon share spent against this
     *                                        delivery (04-pricing-coupons.md #5-#6); '0' when no code applies.
     * @param  ?string  $finalNetAmount  $quote's net/VAT/gross after that discount — always
     *                                   equal to $quote's own amounts when the discount is '0'. Null
     *                                   exactly when $quote is null (no delivery selected/available).
     * @param  list<CouponDeliveryAllocationEntry>  $couponEntries
     */
    public function __construct(
        public array $options,
        public ?string $selectedMethodId,
        public ?int $selectedPickupPointId,
        public ?DeliveryOptionQuote $quote,
        public string $couponDiscountAmount = '0',
        public ?string $finalNetAmount = null,
        public ?string $finalVatAmount = null,
        public ?string $finalGrossAmount = null,
        public array $couponEntries = [],
    ) {}
}
