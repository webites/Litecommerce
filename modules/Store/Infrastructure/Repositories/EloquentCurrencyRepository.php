<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Repositories;

use Litecommerce\Modules\Store\Contracts\CurrencyData;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Infrastructure\Models\ShopCurrency;

final class EloquentCurrencyRepository implements CurrencyRepository
{
    public function base(): ?CurrencyData
    {
        $currency = ShopCurrency::query()->where('is_base', true)->first();

        return $currency === null ? null : $this->toData($currency);
    }

    public function find(string $code): ?CurrencyData
    {
        $currency = ShopCurrency::query()->where('code', $code)->first();

        return $currency === null ? null : $this->toData($currency);
    }

    public function all(): array
    {
        return ShopCurrency::query()->orderByDesc('is_base')->orderBy('code')->get()
            ->map(fn (ShopCurrency $currency) => $this->toData($currency))
            ->all();
    }

    private function toData(ShopCurrency $currency): CurrencyData
    {
        return new CurrencyData(
            code: $currency->code,
            name: $currency->name,
            symbol: $currency->symbol,
            decimalPlaces: $currency->decimal_places,
            isBase: $currency->is_base,
        );
    }
}
