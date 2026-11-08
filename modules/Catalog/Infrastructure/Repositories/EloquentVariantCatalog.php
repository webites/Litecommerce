<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Repositories;

use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Catalog\Contracts\VariantData;
use Litecommerce\Modules\Catalog\Contracts\VariantSummaryData;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;

final class EloquentVariantCatalog implements VariantCatalog
{
    public function __construct(private readonly ProductTypeRegistry $productTypes) {}

    public function find(int $variantId): ?VariantData
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);

        if ($variant === null) {
            return null;
        }

        return new VariantData(
            id: (int) $variant->getKey(),
            productId: $variant->product_id,
            productType: $variant->product->type,
            sku: $variant->sku,
            enabled: $variant->enabled && $this->productTypes->has($variant->product->type),
            unitCode: $variant->product->unit_code,
            minimumQuantity: $variant->product->minimum_quantity,
            maximumQuantity: $variant->product->maximum_quantity,
            quantityStep: $variant->product->quantity_step,
        );
    }

    public function paginateStockTracked(string $locale, int $perPage, int $page): array
    {
        $query = ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->whereIn('type', $this->stockTrackedTypeKeys()))
            ->with([
                'product.translations' => fn ($q) => $q->where('locale', $locale),
                'translations' => fn ($q) => $q->where('locale', $locale),
            ])
            ->orderBy('id');

        $total = (clone $query)->count();
        $variants = $query->forPage($page, $perPage)->get();

        $items = $variants->map(function (ProductVariant $variant): VariantSummaryData {
            $variantTranslation = $variant->translations->first();
            $productTranslation = $variant->product->translations->first();
            $variantName = $variantTranslation === null ? null : $variantTranslation->name;
            $productName = $productTranslation === null ? null : $productTranslation->name;
            $name = $variantName !== null && $variantName !== '' ? $variantName : ($productName ?? 'Produkt #'.$variant->product_id);

            return new VariantSummaryData(
                id: (int) $variant->getKey(),
                productId: $variant->product_id,
                sku: $variant->sku,
                gtin: $variant->gtin ?? '',
                mpn: $variant->mpn ?? '',
                displayName: $name,
                enabled: $variant->enabled && $this->productTypes->has($variant->product->type),
                productType: $variant->product->type,
                minimumQuantity: $variant->product->minimum_quantity,
                maximumQuantity: $variant->product->maximum_quantity,
                quantityStep: $variant->product->quantity_step,
                optionValues: [],
                shortDescription: $variantTranslation?->short_description,
                descriptionHtml: $variantTranslation?->description_html,
            );
        })->values()->all();

        return ['items' => $items, 'total' => $total];
    }

    public function findMany(array $variantIds, string $locale): array
    {
        if ($variantIds === []) {
            return [];
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with([
                'product',
                'product.translations' => fn ($q) => $q->where('locale', $locale),
                'translations' => fn ($q) => $q->where('locale', $locale),
            ])
            ->get();

        $result = [];

        foreach ($variants as $variant) {
            $variantTranslation = $variant->translations->first();
            $productTranslation = $variant->product->translations->first();
            $variantName = $variantTranslation === null ? null : $variantTranslation->name;
            $productName = $productTranslation === null ? null : $productTranslation->name;
            $name = $variantName !== null && $variantName !== '' ? $variantName : ($productName ?? 'Produkt #'.$variant->product_id);

            $result[(int) $variant->getKey()] = new VariantSummaryData(
                id: (int) $variant->getKey(),
                productId: $variant->product_id,
                sku: $variant->sku,
                gtin: $variant->gtin ?? '',
                mpn: $variant->mpn ?? '',
                displayName: $name,
                enabled: $variant->enabled && $this->productTypes->has($variant->product->type),
                productType: $variant->product->type,
                minimumQuantity: $variant->product->minimum_quantity,
                maximumQuantity: $variant->product->maximum_quantity,
                quantityStep: $variant->product->quantity_step,
                optionValues: [],
                shortDescription: $variantTranslation?->short_description,
                descriptionHtml: $variantTranslation?->description_html,
            );
        }

        return $result;
    }

    /** @return list<string> */
    private function stockTrackedTypeKeys(): array
    {
        return array_values(array_map(
            static fn ($type): string => $type->key,
            array_filter($this->productTypes->all(), static fn ($type): bool => $type->tracksStock),
        ));
    }
}
