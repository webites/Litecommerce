<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceCatalog;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceData;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceOverrideData;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPrice;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPriceOverride;

final class EloquentVariantPriceCatalog implements VariantPriceCatalog
{
    public function find(int $variantId): ?VariantPriceData
    {
        $price = VariantPrice::query()->find($variantId);

        if ($price === null) {
            return null;
        }

        $overrides = VariantPriceOverride::query()->where('variant_id', $variantId)->orderBy('currency_code')->get()
            ->map(static fn (VariantPriceOverride $override): VariantPriceOverrideData => new VariantPriceOverrideData(
                currencyCode: $override->currency_code,
                regularAmount: $override->regular_amount,
                regularMode: $override->regular_mode === null ? null : PriceMode::from($override->regular_mode),
                promotionalAmount: $override->promotional_amount,
                promotionalMode: $override->promotional_mode === null ? null : PriceMode::from($override->promotional_mode),
            ))->values()->all();

        return new VariantPriceData(
            variantId: (int) $price->getKey(),
            regularAmount: $price->regular_amount,
            regularMode: PriceMode::from($price->regular_mode),
            promotionalAmount: $price->promotional_amount,
            promotionalMode: $price->promotional_mode === null ? null : PriceMode::from($price->promotional_mode),
            overrides: $overrides,
        );
    }
}
