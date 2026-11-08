<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Application\Concerns\GuardsOpenCart;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

final class SetCartCurrency
{
    use GuardsOpenCart;

    public function __construct(private readonly CurrencyRepository $currencies) {}

    public function execute(int $cartId, string $currencyCode): void
    {
        if ($this->currencies->find($currencyCode) === null) {
            throw new CartValidationException('unknown_currency', 'The currency is unknown.');
        }

        DB::transaction(function () use ($cartId, $currencyCode): void {
            $cart = Cart::query()->lockForUpdate()->findOrFail($cartId);
            $this->assertOpen($cart);
            $cart->update(['currency_code' => $currencyCode, 'version' => $cart->version + 1, 'last_activity_at' => now(), 'status' => 'active']);
        });
    }
}
