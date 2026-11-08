<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceManager;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPrice;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPriceOverride;

final class EloquentVariantPriceManager implements VariantPriceManager
{
    public function setBasePrice(
        int $variantId,
        string $regularAmount,
        PriceMode $regularMode,
        ?string $promotionalAmount,
        ?PriceMode $promotionalMode,
    ): void {
        VariantPrice::query()->updateOrCreate(
            ['variant_id' => $variantId],
            [
                'regular_amount' => $regularAmount,
                'regular_mode' => $regularMode->value,
                'promotional_amount' => $promotionalAmount,
                'promotional_mode' => $promotionalAmount === null ? null : $promotionalMode?->value,
            ],
        );
    }

    public function setCurrencyOverride(
        int $variantId,
        string $currencyCode,
        ?string $regularAmount,
        ?PriceMode $regularMode,
        ?string $promotionalAmount,
        ?PriceMode $promotionalMode,
    ): void {
        $hasAnyOverride = $regularAmount !== null || $promotionalAmount !== null;

        if (! $hasAnyOverride) {
            VariantPriceOverride::query()->where('variant_id', $variantId)->where('currency_code', $currencyCode)->delete();

            return;
        }

        VariantPriceOverride::query()->updateOrCreate(
            ['variant_id' => $variantId, 'currency_code' => $currencyCode],
            [
                'regular_amount' => $regularAmount,
                'regular_mode' => $regularAmount === null ? null : $regularMode?->value,
                'promotional_amount' => $promotionalAmount,
                'promotional_mode' => $promotionalAmount === null ? null : $promotionalMode?->value,
            ],
        );
    }
}
