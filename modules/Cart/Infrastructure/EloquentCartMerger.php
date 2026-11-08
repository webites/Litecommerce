<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Contracts\CartMergeAdjustment;
use Litecommerce\Modules\Cart\Contracts\CartMerger;
use Litecommerce\Modules\Cart\Contracts\CartMergeReport;
use Litecommerce\Modules\Cart\Domain\Quantity;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartCoupon;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartItem;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;

/**
 * Implements "Po logowaniu łączymy" in docs/knowledge-base/05-orders.md:
 * sums shared lines, clamps each to the variant's quantity bounds
 * (covers "cyfrowe maks. 1" without special-casing — Catalog already
 * enforces min=max=step=1 for digital variants) and reports every clamp
 * as an adjustment. The merged cart adopts the guest cart's currency —
 * "waluta z aktualnej przeglądarki" is the device performing the login.
 */
final class EloquentCartMerger implements CartMerger
{
    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly GuestCartCookie $cookie,
    ) {}

    public function mergeGuestCartIntoAccount(int $customerAccountId, Request $request): CartMergeReport
    {
        $guestCartToken = $this->cookie->read($request);

        if ($guestCartToken === null) {
            return CartMergeReport::nothingToMerge();
        }

        $report = DB::transaction(function () use ($customerAccountId, $guestCartToken): CartMergeReport {
            $guestCart = Cart::query()
                ->where('guest_token', $guestCartToken)
                ->where('status', '!=', 'checked_out')
                ->lockForUpdate()
                ->first();

            if ($guestCart === null) {
                return CartMergeReport::nothingToMerge();
            }

            $guestItems = CartItem::query()->where('cart_id', $guestCart->id)->get();

            $accountCart = Cart::query()
                ->where('customer_account_id', $customerAccountId)
                ->where('status', '!=', 'checked_out')
                ->where('id', '!=', $guestCart->id)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            if ($accountCart === null) {
                // No pre-existing account cart: the guest cart is simply
                // promoted, items and all — nothing to sum or clamp.
                $guestCart->update(['customer_account_id' => $customerAccountId, 'guest_token' => null]);

                return new CartMergeReport($guestItems->isNotEmpty(), []);
            }

            $this->moveCoupons($guestCart, $accountCart);

            if ($guestItems->isEmpty()) {
                $guestCart->update(['customer_account_id' => $customerAccountId, 'guest_token' => null, 'status' => 'merged']);

                return CartMergeReport::nothingToMerge();
            }

            $locale = (string) config('app.locale', 'pl');
            $variantIds = $guestItems->map(fn (CartItem $item): int => (int) $item->variant_id)->unique()->values()->all();
            $variantData = $this->variants->findMany($variantIds, $locale);

            $accountItems = CartItem::query()->where('cart_id', $accountCart->id)->get()->keyBy(fn (CartItem $item): int => (int) $item->variant_id);

            $adjustments = [];

            foreach ($guestItems as $guestItem) {
                $variantId = (int) $guestItem->variant_id;
                $variant = $variantData[$variantId] ?? null;
                $accountItem = $accountItems->get($variantId);

                $summed = $accountItem === null ? $guestItem->quantity : Quantity::add($accountItem->quantity, $guestItem->quantity);
                $final = $variant === null ? $summed : Quantity::clamp($summed, $variant->minimumQuantity, $variant->maximumQuantity);

                if (Quantity::compare($final, $summed) !== 0) {
                    $adjustments[] = new CartMergeAdjustment($variantId, $summed, $final);
                }

                if ($accountItem === null) {
                    CartItem::query()->create(['cart_id' => $accountCart->id, 'variant_id' => $variantId, 'quantity' => $final]);
                } else {
                    $accountItem->update(['quantity' => $final]);
                }
            }

            CartItem::query()->where('cart_id', $guestCart->id)->delete();
            $guestCart->update(['customer_account_id' => $customerAccountId, 'guest_token' => null, 'status' => 'merged']);
            $accountCart->update([
                'currency_code' => $guestCart->currency_code,
                'version' => $accountCart->version + 1,
                'last_activity_at' => now(),
                'status' => 'active',
            ]);

            return new CartMergeReport(true, $adjustments);
        });

        $this->cookie->forget();

        return $report;
    }

    /**
     * Carries attached codes into the surviving account cart — "Zachowujemy
     * kody" (05-orders.md). Re-validation of expiry/PIN/account/currency
     * happens naturally on the next price computation (CouponPricer), not
     * here; a guest cart can never have picked up an account-restricted
     * code in the first place, since attaching one always requires the
     * matching account to already be authenticated.
     */
    private function moveCoupons(Cart $guestCart, Cart $accountCart): void
    {
        $guestCodes = CartCoupon::query()->where('cart_id', $guestCart->id)->get();

        if ($guestCodes->isEmpty()) {
            return;
        }

        $existing = CartCoupon::query()->where('cart_id', $accountCart->id)->pluck('code')->all();

        foreach ($guestCodes as $guestCode) {
            if (! in_array($guestCode->code, $existing, true)) {
                CartCoupon::query()->create(['cart_id' => $accountCart->id, 'code' => $guestCode->code, 'attached_at' => $guestCode->attached_at]);
            }
        }

        CartCoupon::query()->where('cart_id', $guestCart->id)->delete();
    }
}
