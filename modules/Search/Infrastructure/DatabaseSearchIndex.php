<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Infrastructure;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Catalog\Contracts\SearchDocumentData;
use Litecommerce\Modules\Catalog\Contracts\SearchDocumentSource;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Search\Contracts\SearchIndex;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Warehouse\Contracts\StockItemData;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;
use Throwable;

final readonly class DatabaseSearchIndex implements SearchIndex
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private SearchDocumentSource $documents,
        private PriceQuoter $prices,
        private StockLevelCatalog $stock,
        private CurrencyRepository $currencies,
        private SearchTextNormalizer $normalizer,
    ) {}

    public function ensureReady(): void
    {
        $activeGeneration = DB::table('search_index_states')->where('id', 1)->value('active_generation');
        $dirty = (bool) DB::table('search_index_states')->where('id', 1)->value('dirty');

        if (is_string($activeGeneration) && ! $dirty) {
            return;
        }

        $claimed = DB::table('search_index_states')->where('id', 1)->where('dirty', true)
            ->where(function ($query): void {
                $query->whereNull('rebuilding_at')->orWhere('rebuilding_at', '<', now()->subMinutes(5));
            })
            ->update(['rebuilding_at' => now(), 'updated_at' => now()]);

        if ($claimed === 1 || ! is_string($activeGeneration)) {
            $this->rebuild();
        }
    }

    public function rebuild(): void
    {
        $generation = (string) Str::uuid();
        $after = 0;

        try {
            while (true) {
                $batch = $this->documents->batchAfter($after, self::BATCH_SIZE);
                $items = $batch['items'];
                $lastVariantId = $batch['lastVariantId'];

                if ($lastVariantId === null) {
                    break;
                }

                $this->storeBatch($generation, $items);
                $after = $lastVariantId;
            }

            DB::transaction(function () use ($generation): void {
                $previous = DB::table('search_index_states')->where('id', 1)->lockForUpdate()->value('active_generation');
                DB::table('search_index_states')->where('id', 1)->update([
                    'active_generation' => $generation,
                    'dirty' => false,
                    'rebuilding_at' => null,
                    'indexed_at' => now(),
                    'updated_at' => now(),
                ]);

                if (is_string($previous) && $previous !== $generation) {
                    $this->deleteGeneration($previous);
                }
            });
        } catch (Throwable $exception) {
            $this->deleteGeneration($generation);
            DB::table('search_index_states')->where('id', 1)->update(['rebuilding_at' => null, 'dirty' => true, 'updated_at' => now()]);
            throw $exception;
        }
    }

    public function markDirty(): void
    {
        DB::table('search_index_states')->where('id', 1)->update(['dirty' => true, 'updated_at' => now()]);
    }

    public function refreshPrices(array $variantIds): void
    {
        $generation = $this->activeGeneration();
        $variantIds = $this->existingVariantIds($generation, $variantIds);

        if ($generation === null || $variantIds === []) {
            return;
        }

        DB::table('search_variant_prices')->where('generation', $generation)->whereIn('variant_id', $variantIds)->delete();
        $this->storePrices($generation, $variantIds);
    }

    public function refreshAvailability(array $variantIds): void
    {
        $generation = $this->activeGeneration();
        $variantIds = $this->existingVariantIds($generation, $variantIds);

        if ($generation === null || $variantIds === []) {
            return;
        }

        $types = DB::table('search_documents')->where('generation', $generation)->whereIn('variant_id', $variantIds)
            ->pluck('product_type', 'variant_id')->all();
        $stock = $this->stock->findMany($variantIds);
        $pricedVariantIds = $this->pricedVariantIds($variantIds);

        foreach ($variantIds as $variantId) {
            DB::table('search_variant_availability')->updateOrInsert(
                ['generation' => $generation, 'variant_id' => $variantId],
                ['available' => $this->isAvailable((string) ($types[$variantId] ?? ''), $stock[$variantId] ?? null, isset($pricedVariantIds[$variantId]))],
            );
        }
    }

    /** @param list<SearchDocumentData> $items */
    private function storeBatch(string $generation, array $items): void
    {
        if ($items === []) {
            return;
        }

        $variantTypes = [];
        foreach ($items as $item) {
            $attributeText = implode(' ', array_map(static fn ($attribute): string => $attribute->definitionName.' '.$attribute->valueName, $item->attributes));
            $documentId = DB::table('search_documents')->insertGetId([
                'generation' => $generation,
                'product_id' => $item->productId,
                'variant_id' => $item->variantId,
                'locale' => $item->locale,
                'slug' => $item->slug,
                'product_type' => $item->productType,
                'product_name' => $item->productName,
                'variant_name' => $item->variantName,
                'sku' => $item->sku,
                'variant_path' => $item->variantPath,
                'exact_name' => $this->normalizer->phrase($item->variantName !== '' ? $item->variantName : $item->productName),
                'name_terms' => $this->normalizer->paddedTerms($item->productName.' '.$item->variantName.' '.$item->sku, $item->locale),
                'attribute_terms' => $this->normalizer->paddedTerms($attributeText, $item->locale),
                'category_terms' => $this->normalizer->paddedTerms($item->categoryText, $item->locale),
                'description_terms' => $this->normalizer->paddedTerms($item->shortDescription.' '.$item->description, $item->locale),
            ]);

            foreach ($item->attributes as $attribute) {
                DB::table('search_document_attributes')->insert([
                    'search_document_id' => $documentId,
                    'definition_key' => $attribute->definitionKey,
                    'definition_name' => $attribute->definitionName,
                    'value_key' => $attribute->valueKey,
                    'value_name' => $attribute->valueName,
                    'filterable' => $attribute->filterable,
                ]);
            }
            foreach ($item->categoryIds as $categoryId) {
                DB::table('search_document_categories')->insert([
                    'search_document_id' => $documentId,
                    'category_id' => $categoryId,
                ]);
            }
            $variantTypes[$item->variantId] = $item->productType;
        }

        $variantIds = array_map('intval', array_keys($variantTypes));
        $stock = $this->stock->findMany($variantIds);
        $pricedVariantIds = $this->pricedVariantIds($variantIds);
        foreach ($variantTypes as $variantId => $productType) {
            DB::table('search_variant_availability')->insert([
                'generation' => $generation,
                'variant_id' => $variantId,
                'available' => $this->isAvailable($productType, $stock[$variantId] ?? null, isset($pricedVariantIds[$variantId])),
            ]);
        }
        $this->storePrices($generation, $variantIds);
    }

    /** @param list<int> $variantIds */
    private function storePrices(string $generation, array $variantIds): void
    {
        foreach ($this->currencies->all() as $currency) {
            $quotes = $this->prices->quoteMany($variantIds, $currency->code, '1');
            foreach ($quotes as $quote) {
                DB::table('search_variant_prices')->insert([
                    'generation' => $generation,
                    'variant_id' => $quote->variantId,
                    'currency_code' => $currency->code,
                    'effective_amount' => $quote->unitEffectiveAmount,
                    'regular_amount' => $quote->regularUnitAmount,
                    'promotional_active' => $quote->promotionalActive,
                ]);
            }
        }
    }

    private function isAvailable(string $productType, ?StockItemData $stock, bool $hasPrice): bool
    {
        if (! $hasPrice) {
            return false;
        }

        if ($productType === ProductType::Digital->value) {
            return true;
        }

        return $stock !== null && ($stock->allowBackorder || bccomp($stock->availableQuantity, '0', 6) > 0);
    }

    /**
     * Quoted in the base currency specifically — never confused with a
     * missing exchange rate for some OTHER currency, since the base price
     * itself never needs conversion.
     *
     * @param  list<int>  $variantIds
     * @return array<int,true>
     */
    private function pricedVariantIds(array $variantIds): array
    {
        $baseCurrency = $this->currencies->base();

        if ($baseCurrency === null || $variantIds === []) {
            return [];
        }

        $priced = [];
        foreach ($this->prices->quoteMany($variantIds, $baseCurrency->code, '1') as $quote) {
            $priced[$quote->variantId] = true;
        }

        return $priced;
    }

    private function activeGeneration(): ?string
    {
        $generation = DB::table('search_index_states')->where('id', 1)->value('active_generation');

        return is_string($generation) ? $generation : null;
    }

    /** @param list<int> $variantIds @return list<int> */
    private function existingVariantIds(?string $generation, array $variantIds): array
    {
        if ($generation === null || $variantIds === []) {
            return [];
        }

        return DB::table('search_documents')->where('generation', $generation)
            ->whereIn('variant_id', array_values(array_unique(array_map('intval', $variantIds))))
            ->distinct()->pluck('variant_id')->map(static fn ($id): int => (int) $id)->all();
    }

    private function deleteGeneration(string $generation): void
    {
        DB::table('search_variant_prices')->where('generation', $generation)->delete();
        DB::table('search_variant_availability')->where('generation', $generation)->delete();
        DB::table('search_documents')->where('generation', $generation)->delete();
    }
}
