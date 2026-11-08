<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Application\Concerns\GuardsOpenCart;
use Litecommerce\Modules\Cart\Domain\Quantity;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Cart\Infrastructure\Models\CartItem;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;

/**
 * Sets a line's quantity to exactly $quantity (unlike AddCartItem, which
 * sums). A zero quantity removes the line — the doc doesn't distinguish
 * "set to zero" from "remove" as separate customer actions.
 */
final class UpdateCartItemQuantity
{
    use GuardsOpenCart;

    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly PriceQuoter $prices,
    ) {}

    public function execute(int $cartId, int $variantId, string $quantity, string $locale): void
    {
        if (Quantity::compare($quantity, '0') < 0) {
            throw new CartValidationException('invalid_quantity', 'The quantity is invalid.');
        }

        DB::transaction(function () use ($cartId, $variantId, $quantity, $locale): void {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cartId);
            $this->assertOpen($cart);
            $item = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variantId)->first();

            if ($item === null) {
                throw new CartValidationException('item_not_found', 'The item is not in the cart.');
            }

            if (Quantity::compare($quantity, '0') === 0) {
                $item->delete();
            } else {
                $variant = $this->variants->findMany([$variantId], $locale)[$variantId] ?? null;

                if ($variant === null || ! $variant->enabled) {
                    throw new CartValidationException('variant_unavailable', 'The product is unavailable.');
                }

                if ($this->prices->quote($variantId, $cart->currency_code, $quantity) === null) {
                    throw new CartValidationException('no_price', 'The product has no price yet.');
                }

                if (! Quantity::isWithinBounds($quantity, $variant->minimumQuantity, $variant->maximumQuantity, $variant->quantityStep)) {
                    throw new CartValidationException('quantity_out_of_bounds', 'The requested quantity is out of range.');
                }

                $item->update(['quantity' => $quantity]);
            }

            $cart->update(['version' => $cart->version + 1, 'last_activity_at' => now(), 'status' => 'active']);
        });
    }
}
