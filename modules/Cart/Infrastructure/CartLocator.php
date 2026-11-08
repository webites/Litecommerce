<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure;

use Illuminate\Support\Str;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;

/**
 * Cart row lookup/creation. "Open" means not checked out yet — a cart
 * whose checkout was confirmed is left alone (history for the future
 * Orders integration point) and a fresh one is created for further
 * shopping instead, per "Zatwierdzenie zamyka wersję źródła" in
 * docs/knowledge-base/05-orders.md.
 */
final class CartLocator
{
    public function findOpenForAccount(int $customerAccountId): ?Cart
    {
        return Cart::query()
            ->where('customer_account_id', $customerAccountId)
            ->where('status', '!=', 'checked_out')
            ->orderByDesc('id')
            ->first();
    }

    public function findOpenForGuestToken(string $guestToken): ?Cart
    {
        return Cart::query()
            ->where('guest_token', $guestToken)
            ->where('status', '!=', 'checked_out')
            ->first();
    }

    public function createForAccount(int $customerAccountId, string $currencyCode): Cart
    {
        return Cart::query()->create([
            'customer_account_id' => $customerAccountId,
            'guest_token' => null,
            'currency_code' => $currencyCode,
            'status' => 'active',
            'version' => 0,
            'last_activity_at' => now(),
        ]);
    }

    public function createForGuest(string $currencyCode): Cart
    {
        return Cart::query()->create([
            'customer_account_id' => null,
            'guest_token' => (string) Str::uuid(),
            'currency_code' => $currencyCode,
            'status' => 'active',
            'version' => 0,
            'last_activity_at' => now(),
        ]);
    }
}
