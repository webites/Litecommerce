<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Contracts;

/**
 * The one-time gate for adding a code to a cart — PIN verification (with
 * attempt limiting) happens here, exactly once, so the cart never has to
 * store the PIN itself. Everything after a successful attach is re-checked
 * read-only by CouponPricer on every price recompute; a code that goes
 * invalid later (expired, blocked, exhausted) simply stops discounting
 * without needing another PIN.
 */
interface CouponAttachmentValidator
{
    /**
     * @param  list<string>  $alreadyAttachedCodes  Other codes already on this cart, to enforce
     *                                              "łączenie kuponów konfigurowalne" (exclusive codes).
     */
    public function attach(
        string $code,
        ?string $pin,
        ?int $customerAccountId,
        string $currencyCode,
        array $alreadyAttachedCodes,
    ): CouponAttachmentStatus;
}
