<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartCoupon;
use Litecommerce\Modules\Coupons\Contracts\CouponAttachmentStatus;
use Litecommerce\Modules\Coupons\Contracts\CouponAttachmentValidator;

/**
 * The one-time PIN-checking gate for adding a code to the cart — see
 * Coupons\Contracts\CouponAttachmentValidator. Once attached, the code is
 * re-validated read-only on every price recompute by PriceLines; it stays
 * attached (simply contributing nothing) if it later goes invalid, the
 * same way an unavailable product line stays visible instead of vanishing.
 */
final class AttachCouponCode
{
    public function __construct(private readonly CouponAttachmentValidator $validator) {}

    public function execute(int $cartId, string $code, ?string $pin, ?int $customerAccountId): void
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            throw new CartValidationException('coupon_code_required', 'A coupon code is required.');
        }

        if (CartCoupon::query()->where('cart_id', $cartId)->where('code', $code)->exists()) {
            return;
        }

        $cart = Cart::query()->findOrFail($cartId);
        $alreadyAttached = CartCoupon::query()->where('cart_id', $cartId)->pluck('code')->all();

        // Deliberately outside the transaction below: a PIN attempt this
        // records (Coupons\Application\ValidateCouponAttachment) must
        // durably count even when the overall attach ends in rejection —
        // nesting it inside a transaction we then roll back on the thrown
        // exception would silently undo the attempt counter every time.
        $status = $this->validator->attach($code, $pin, $customerAccountId, $cart->currency_code, $alreadyAttached);

        if ($status !== CouponAttachmentStatus::Applied) {
            throw new CartValidationException('coupon_'.$status->value, 'The coupon code was rejected: '.$status->value.'.');
        }

        DB::transaction(function () use ($cartId, $code): void {
            Cart::query()->lockForUpdate()->findOrFail($cartId);

            if (! CartCoupon::query()->where('cart_id', $cartId)->where('code', $code)->exists()) {
                CartCoupon::query()->create(['cart_id' => $cartId, 'code' => $code, 'attached_at' => now()]);
            }
        });
    }
}
