<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

/**
 * One cart/draft line as Coupons needs to see it to allocate a discount —
 * never the whole PricedLine, so Coupons stays ignorant of Catalog/Pricing
 * shapes. $key is opaque to Coupons and simply echoed back in the result
 * (Cart uses its variant id); ordering of the input list is the "stabilny
 * identyfikator pozycji" tie-break for largest-remainder rounding, so the
 * caller must always build it in the same stable order (e.g. ascending
 * variant id).
 */
final readonly class CouponLineContext
{
    public function __construct(
        public int|string $key,
        public string $grossAmount,
        public bool $promotional,
    ) {}
}
