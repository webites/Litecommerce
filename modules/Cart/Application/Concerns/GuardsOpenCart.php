<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application\Concerns;

use Litecommerce\Modules\Cart\Application\CartValidationException;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;

/**
 * A cart whose checkout was already confirmed is closed to further
 * mutation — a caller holding a stale resolved id from just before
 * confirmation should get a clear rejection, not silently add to a cart
 * nobody will ever look at again. See ConfirmCheckoutDraft, which locks
 * the same row before closing it.
 */
trait GuardsOpenCart
{
    private function assertOpen(Cart $cart): void
    {
        if ($cart->status === 'checked_out') {
            throw new CartValidationException('cart_checked_out', 'The cart has already been checked out.');
        }
    }
}
