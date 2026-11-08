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
 * Adding an existing line's variant again sums onto its quantity rather
 * than replacing it (see UpdateCartItemQuantity for "set exact"). Every
 * cart mutation locks the cart row first, which serializes concurrent
 * requests against the same cart without needing a separate lock per
 * item row.
 */
final class AddCartItem
{
    use GuardsOpenCart;

    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly PriceQuoter $prices,
    ) {}

    public function execute(int $cartId, int $variantId, string $quantity, string $locale): void
    {
        if (! Quantity::isPositive($quantity)) {
            throw new CartValidationException('invalid_quantity', 'The quantity is invalid.');
        }

        DB::transaction(function () use ($cartId, $variantId, $quantity, $locale): void {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cartId);
            $this->assertOpen($cart);
            $variant = $this->variants->findMany([$variantId], $locale)[$variantId] ?? null;

            if ($variant === null || ! $variant->enabled) {
                throw new CartValidationException('variant_unavailable', 'The product is unavailable.');
            }

            if ($this->prices->quote($variantId, $cart->currency_code, $quantity) === null) {
                throw new CartValidationException('no_price', 'The product has no price yet.');
            }

            $existing = CartItem::query()->where('cart_id', $cart->id)->where('variant_id', $variantId)->first();
            $newQuantity = $existing === null ? $quantity : Quantity::add($existing->quantity, $quantity);

            if (! Quantity::isWithinBounds($newQuantity, $variant->minimumQuantity, $variant->maximumQuantity, $variant->quantityStep)) {
                throw new CartValidationException('quantity_out_of_bounds', 'The requested quantity is out of range.');
            }

            if ($existing === null) {
                CartItem::query()->create(['cart_id' => $cart->id, 'variant_id' => $variantId, 'quantity' => $newQuantity]);
            } else {
                $existing->update(['quantity' => $newQuantity]);
            }

            $cart->update(['version' => $cart->version + 1, 'last_activity_at' => now(), 'status' => 'active']);
        });
    }
}
