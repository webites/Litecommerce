<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Litecommerce\Modules\Store\Infrastructure\Models\ShopCurrency;

/**
 * Adds a non-base currency to the shop. The base currency is exclusively
 * managed by SetBaseCurrency (wizard) — this never sets is_base=true.
 */
final class AddCurrency
{
    public function handle(string $code, string $name, string $symbol, int $decimalPlaces): ShopCurrency
    {
        return ShopCurrency::query()->create([
            'code' => $code,
            'name' => $name,
            'symbol' => $symbol,
            'decimal_places' => $decimalPlaces,
            'is_base' => false,
        ]);
    }
}
