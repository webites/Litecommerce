<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Infrastructure;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Search\Contracts\ProductSearchCriteria;
use Litecommerce\Modules\Search\Contracts\ProductSearchEngine;
use Litecommerce\Modules\Search\Contracts\ProductSearchResult;
use Litecommerce\Modules\Search\Contracts\SearchFilterData;
use Litecommerce\Modules\Search\Contracts\SearchFilterValueData;
use Litecommerce\Modules\Search\Contracts\SearchIndex;
use Litecommerce\Modules\Search\Contracts\SearchProductHitData;
use Litecommerce\Modules\Search\Contracts\SearchVariantHitData;

final readonly class DatabaseProductSearchEngine implements ProductSearchEngine
{
    public function __construct(
        private SearchIndex $index,
        private ProductCatalog $products,
        private SearchTextNormalizer $normalizer,
    ) {}

    public function search(ProductSearchCriteria $criteria): ProductSearchResult
    {
        $this->index->ensureReady();
        $generation = DB::table('search_index_states')->where('id', 1)->value('active_generation');

        if (! is_string($generation)) {
            return new ProductSearchResult([], [], 0);
        }

        $matched = $this->matchingDocuments($criteria, $generation);
        $total = (clone $matched)->count();
        $variantRows = $matched
            ->orderByDesc('a.available')
            ->orderByDesc('relevance')
            ->orderByDesc('d.product_id')
            ->orderBy('d.variant_id')
            ->forPage($criteria->page, $criteria->perPage)
            ->get();
        $productIds = $variantRows->pluck('product_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();

        if ($productIds === []) {
            return new ProductSearchResult([], $this->filters($generation, $criteria), $total);
        }

        $summaries = $this->products->findManyPublished($productIds, $criteria->locale);
        $summariesById = [];
        foreach ($summaries as $summary) {
            $summariesById[$summary->id] = $summary;
        }

        $items = [];
        foreach ($variantRows as $row) {
            $productId = (int) $row->product_id;
            if (! isset($summariesById[$productId])) {
                continue;
            }

            $items[] = new SearchProductHitData(
                $summariesById[$productId],
                new SearchVariantHitData(
                    variantId: (int) $row->variant_id,
                    name: (string) $row->variant_name,
                    sku: (string) $row->sku,
                    path: (string) $row->variant_path,
                    unitAmount: $row->effective_amount === null ? null : (string) $row->effective_amount,
                    regularUnitAmount: $row->regular_amount === null ? null : (string) $row->regular_amount,
                    promotionalActive: (bool) ($row->promotional_active ?? false),
                    available: (bool) $row->available,
                ),
            );
        }

        return new ProductSearchResult($items, $this->filters($generation, $criteria), $total);
    }

    private function matchingDocuments(ProductSearchCriteria $criteria, string $generation): Builder
    {
        $query = DB::table('search_documents as d')
            ->join('search_variant_availability as a', function ($join): void {
                $join->on('a.generation', '=', 'd.generation')->on('a.variant_id', '=', 'd.variant_id');
            })
            ->leftJoin('search_variant_prices as p', function ($join) use ($criteria): void {
                $join->on('p.generation', '=', 'd.generation')->on('p.variant_id', '=', 'd.variant_id')
                    ->where('p.currency_code', $criteria->currencyCode);
            })
            ->where('d.generation', $generation)
            ->where('d.locale', $criteria->locale);

        $tokens = $this->normalizer->tokens(mb_substr($criteria->query, 0, 100), $criteria->locale);
        foreach ($tokens as $token) {
            $needle = '% '.$token.' %';
            $query->where(function (Builder $words) use ($needle): void {
                $words->where('d.name_terms', 'like', $needle)
                    ->orWhere('d.attribute_terms', 'like', $needle)
                    ->orWhere('d.category_terms', 'like', $needle)
                    ->orWhere('d.description_terms', 'like', $needle);
            });
        }

        if ($criteria->categoryId !== null) {
            $query->whereExists(function (Builder $category) use ($criteria): void {
                $category->selectRaw('1')->from('search_document_categories as dc')
                    ->whereColumn('dc.search_document_id', 'd.id')->where('dc.category_id', $criteria->categoryId);
            });
        }
        if ($criteria->minimumPrice !== null) {
            $query->where('p.effective_amount', '>=', $criteria->minimumPrice);
        }
        if ($criteria->maximumPrice !== null) {
            $query->where('p.effective_amount', '<=', $criteria->maximumPrice);
        }
        if ($criteria->availableOnly) {
            $query->where('a.available', true);
        }
        foreach (array_slice($criteria->attributes, 0, 12, true) as $definition => $values) {
            $values = array_slice(array_values(array_unique($values)), 0, 20);
            if ($values === []) {
                continue;
            }
            $query->whereExists(function (Builder $attribute) use ($definition, $values): void {
                $attribute->selectRaw('1')->from('search_document_attributes as da')
                    ->whereColumn('da.search_document_id', 'd.id')
                    ->where('da.filterable', true)
                    ->where('da.definition_key', $definition)
                    ->whereIn('da.value_key', $values);
            });
        }

        $phrase = $this->normalizer->phrase($criteria->query);
        $scoreSql = 'CASE WHEN ? <> \'\' AND LOWER(d.sku) = ? THEN 1200 ELSE 0 END'
            .' + CASE WHEN ? <> \'\' AND d.exact_name = ? THEN 1000 ELSE 0 END';
        $bindings = [$phrase, $phrase, $phrase, $phrase];
        foreach ($tokens as $token) {
            $needle = '% '.$token.' %';
            $scoreSql .= ' + CASE WHEN d.name_terms LIKE ? THEN 80 ELSE 0 END'
                .' + CASE WHEN d.attribute_terms LIKE ? THEN 40 ELSE 0 END'
                .' + CASE WHEN d.category_terms LIKE ? THEN 25 ELSE 0 END'
                .' + CASE WHEN d.description_terms LIKE ? THEN 10 ELSE 0 END';
            array_push($bindings, $needle, $needle, $needle, $needle);
        }

        return $query->select([
            'd.id', 'd.product_id', 'd.variant_id', 'd.product_name', 'd.variant_name', 'd.sku', 'd.variant_path',
            'a.available', 'p.effective_amount', 'p.regular_amount', 'p.promotional_active',
        ])->selectRaw("({$scoreSql}) AS relevance", $bindings);
    }

    /** @return list<SearchFilterData> */
    private function filters(string $generation, ProductSearchCriteria $criteria): array
    {
        $query = DB::table('search_document_attributes as a')
            ->join('search_documents as d', 'd.id', '=', 'a.search_document_id')
            ->where('d.generation', $generation)->where('d.locale', $criteria->locale)->where('a.filterable', true);

        if ($criteria->categoryId !== null) {
            $query->whereExists(function (Builder $category) use ($criteria): void {
                $category->selectRaw('1')->from('search_document_categories as dc')
                    ->whereColumn('dc.search_document_id', 'd.id')->where('dc.category_id', $criteria->categoryId);
            });
        }

        $rows = $query
            ->select(['a.definition_key', 'a.definition_name', 'a.value_key', 'a.value_name'])
            ->distinct()->orderBy('a.definition_name')->orderBy('a.value_name')->limit(500)->get();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row->definition_key]['name'] = (string) $row->definition_name;
            $grouped[(string) $row->definition_key]['values'][(string) $row->value_key] = (string) $row->value_name;
        }

        $filters = [];
        foreach ($grouped as $key => $filter) {
            $values = [];
            foreach ($filter['values'] as $valueKey => $name) {
                $values[] = new SearchFilterValueData($valueKey, $name);
            }
            $filters[] = new SearchFilterData($key, $filter['name'], $values);
        }

        return $filters;
    }
}
