<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Cart\Application\Concerns\GuardsOpenCart;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Identity\Contracts\BuyerProfileType;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;

final class CreateCheckoutDraft
{
    use GuardsOpenCart;

    public function __construct(private readonly CustomerAccountLookup $customers) {}

    public function execute(int $cartId, ?int $customerAccountId): CheckoutDraft
    {
        return DB::transaction(function () use ($cartId, $customerAccountId): CheckoutDraft {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cartId);
            $this->assertOpen($cart);

            if ($cart->items()->doesntExist()) {
                throw new CartValidationException('empty_cart', 'Koszyk jest pusty.');
            }

            $existing = CheckoutDraft::query()
                ->where('cart_id', $cart->id)
                ->where('status', 'open')
                ->latest('created_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return CheckoutDraft::query()->create([
                'id' => (string) Str::uuid(),
                'cart_id' => $cart->id,
                'cart_version_seen' => $cart->version,
                'status' => 'open',
                'buyer_type' => BuyerProfileType::Person->value,
                'customer_account_id' => $customerAccountId,
                // Snapshotted once here so confirm-readiness only ever
                // checks "is email set" instead of doing a fresh Identity
                // lookup — a guest fills it in later via UpdateCheckoutDraft.
                'email' => $customerAccountId === null ? null : $this->customers->emailFor($customerAccountId),
                'currency_code' => $cart->currency_code,
                'ships_to_different_address' => false,
                'version' => 0,
            ]);
        });
    }
}
