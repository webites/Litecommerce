<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Application\Concerns\GuardsOpenCart;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartItem;

final class RemoveCartItem
{
    use GuardsOpenCart;

    public function execute(int $cartId, int $variantId): void
    {
        DB::transaction(function () use ($cartId, $variantId): void {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cartId);
            $this->assertOpen($cart);
            CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variantId)->delete();
            $cart->update(['version' => $cart->version + 1, 'last_activity_at' => now(), 'status' => 'active']);
        });
    }
}
