<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Litecommerce\Modules\Catalog\Contracts\ProductSummaryData;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemAssembler;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemData;
use Litecommerce\Modules\Warehouse\Contracts\StockItemData;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;

final readonly class PricingAwareProductListingItemAssembler implements ProductListingItemAssembler
{
    public function __construct(private PriceQuoter $prices, private StockLevelCatalog $stock, private CurrencyRepository $currencies) {}

    public function assemble(array $items, string $currencyCode): array
    {
        $variantIds = array_values(array_filter(array_map(static fn (ProductSummaryData $item): int => $item->defaultVariantId, $items)));
        $stockByVariant = $this->stock->findMany($variantIds);
        $quotesByVariant = $this->prices->quoteMany($variantIds, $currencyCode, '1');
        $currency = $this->currencies->find($currencyCode);
        $currencyDecimalPlaces = $currency === null ? 2 : $currency->decimalPlaces;

        return array_map(
            function (ProductSummaryData $item) use ($currencyCode, $currencyDecimalPlaces, $stockByVariant, $quotesByVariant): ProductListingItemData {
                $quote = $quotesByVariant[$item->defaultVariantId] ?? null;

                return new ProductListingItemData(
                    id: $item->id,
                    slug: $item->slug,
                    name: $item->name,
                    shortDescription: $item->shortDescription,
                    imageUrl: $item->imageUrl,
                    unitAmount: $quote?->unitEffectiveAmount,
                    regularUnitAmount: $quote?->regularUnitAmount,
                    promotionalActive: $quote !== null && $quote->promotionalActive,
                    currencyCode: $currencyCode,
                    currencyDecimalPlaces: $currencyDecimalPlaces,
                    available: $this->isAvailable($item->type, $stockByVariant[$item->defaultVariantId] ?? null),
                    defaultVariantId: $item->defaultVariantId,
                    imageSrcset: $item->imageSrcset,
                    imageWidth: $item->imageWidth,
                    imageHeight: $item->imageHeight,
                );
            },
            $items,
        );
    }

    private function isAvailable(string $productType, ?StockItemData $stockItem): bool
    {
        if ($productType === ProductType::Digital->value) {
            return true;
        }

        if ($stockItem === null) {
            return false;
        }

        return bccomp($stockItem->availableQuantity, '0', 6) > 0 || $stockItem->allowBackorder;
    }
}
