<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Store\Infrastructure\Models\ShopCurrency;

/**
 * Sets the store's base currency. Exactly one currency has is_base=true —
 * MySQL has no partial unique index, so the invariant is enforced here,
 * in a transaction, not by a database constraint.
 */
final class SetBaseCurrency
{
    public function handle(string $code, string $name, string $symbol, int $decimalPlaces): void
    {
        DB::transaction(function () use ($code, $name, $symbol, $decimalPlaces) {
            ShopCurrency::query()->where('is_base', true)->update(['is_base' => false]);

            ShopCurrency::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'symbol' => $symbol, 'decimal_places' => $decimalPlaces, 'is_base' => true],
            );
        });
    }
}
