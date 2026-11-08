<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductStatus;
use Litecommerce\Modules\Catalog\Contracts\SearchDocumentAttributeData;
use Litecommerce\Modules\Catalog\Contracts\SearchDocumentData;
use Litecommerce\Modules\Catalog\Contracts\SearchDocumentSource;

final class EloquentSearchDocumentSource implements SearchDocumentSource
{
    public function batchAfter(int $afterVariantId, int $limit): array
    {
        $variants = DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('v.id', '>', $afterVariantId)
            ->where('v.enabled', true)
            ->whereNull('v.archived_at')
            ->where('p.status', ProductStatus::Published->value)
            ->orderBy('v.id')
            ->limit(max(1, min(500, $limit)))
            ->get(['v.id', 'v.product_id', 'v.sku', 'p.type']);

        if ($variants->isEmpty()) {
            return ['items' => [], 'lastVariantId' => null];
        }

        $variantIds = $variants->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $productIds = $variants->pluck('product_id')->map(static fn ($id): int => (int) $id)->unique()->values()->all();
        $productTranslations = DB::table('product_translations')->whereIn('product_id', $productIds)->get()->groupBy('product_id');
        $variantTranslations = DB::table('variant_translations')->whereIn('product_variant_id', $variantIds)->get()
            ->groupBy('product_variant_id')->map(static fn ($rows) => $rows->keyBy('locale'));
        $categoryData = $this->categories($productIds);
        $productAttributes = $this->productAttributes($productIds);
        $variantAttributes = $this->variantAttributes($variantIds);
        $options = $this->optionAttributes($variantIds);
        $items = [];

        foreach ($variants as $variant) {
            foreach ($productTranslations->get($variant->product_id, collect()) as $translation) {
                $locale = (string) $translation->locale;
                $variantTranslation = $variantTranslations->get($variant->id, collect())->get($locale);
                $base = $productAttributes[(int) $variant->product_id][$locale] ?? [];
                $overrides = $variantAttributes[(int) $variant->id][$locale] ?? [];
                $optionValues = $options[(int) $variant->id][$locale] ?? [];
                $effective = $this->effectiveAttributes($base, $overrides, $optionValues);
                $category = $categoryData[(int) $variant->product_id][$locale] ?? ['ids' => [], 'text' => ''];
                $optionNames = array_map(
                    static fn (array $attributes): string => (string) $attributes[0]['valueName'],
                    array_values($optionValues),
                );
                $customVariantName = isset($variantTranslation->name) ? trim((string) $variantTranslation->name) : '';
                $variantName = $customVariantName !== ''
                    ? $customVariantName
                    : (string) $translation->name.($optionNames === [] ? '' : ' — '.implode(' / ', $optionNames));
                $path = implode('/', array_merge(...array_map(
                    static fn (array $attributes): array => [$attributes[0]['definitionSlug'], $attributes[0]['valueSlug']],
                    array_values($optionValues),
                )));

                $items[] = new SearchDocumentData(
                    productId: (int) $variant->product_id,
                    variantId: (int) $variant->id,
                    locale: $locale,
                    slug: (string) $translation->slug,
                    productType: (string) $variant->type,
                    productName: (string) $translation->name,
                    variantName: $variantName,
                    sku: (string) $variant->sku,
                    shortDescription: trim(implode(' ', array_filter([
                        $translation->short_description,
                        $variantTranslation?->short_description,
                    ]))),
                    description: trim(strip_tags(implode(' ', array_filter([
                        $translation->description_html,
                        $variantTranslation?->description_html,
                    ])))),
                    variantPath: $path,
                    categoryText: $category['text'],
                    categoryIds: $category['ids'],
                    attributes: array_map(
                        static fn (array $attribute): SearchDocumentAttributeData => new SearchDocumentAttributeData(
                            definitionKey: $attribute['definitionKey'],
                            definitionName: $attribute['definitionName'],
                            valueKey: $attribute['valueKey'],
                            valueName: $attribute['valueName'],
                            filterable: $attribute['filterable'],
                        ),
                        array_values($effective),
                    ),
                );
            }
        }

        return ['items' => $items, 'lastVariantId' => (int) $variants->last()->id];
    }

    /** @return array<int, array<string, array{ids:list<int>,text:string}>> */
    private function categories(array $productIds): array
    {
        $parents = DB::table('categories')->pluck('parent_id', 'id')->all();
        $assigned = DB::table('product_categories')->whereIn('product_id', $productIds)->get()->groupBy('product_id');
        $idsByProduct = [];
        $allIds = [];

        foreach ($assigned as $productId => $rows) {
            $ids = [];
            foreach ($rows as $row) {
                $id = (int) $row->category_id;
                while ($id > 0 && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $id = (int) ($parents[$id] ?? 0);
                }
            }
            $idsByProduct[(int) $productId] = $ids;
            $allIds = [...$allIds, ...$ids];
        }

        $translations = DB::table('category_translations')->whereIn('category_id', array_values(array_unique($allIds)))->get();
        $names = [];
        foreach ($translations as $translation) {
            $names[(int) $translation->category_id][(string) $translation->locale] = (string) $translation->name;
        }

        $result = [];
        foreach ($idsByProduct as $productId => $ids) {
            $locales = [];
            foreach ($ids as $id) {
                foreach ($names[$id] ?? [] as $locale => $name) {
                    $locales[$locale][] = $name;
                }
            }
            foreach ($locales as $locale => $localeNames) {
                $result[$productId][$locale] = ['ids' => $ids, 'text' => implode(' ', $localeNames)];
            }
        }

        return $result;
    }

    /** @return array<int, array<string, array<string, array<string, mixed>>>> */
    private function productAttributes(array $productIds): array
    {
        $rows = DB::table('product_attribute_values as pav')
            ->join('attribute_definitions as d', 'd.id', '=', 'pav.attribute_definition_id')
            ->join('attribute_definition_translations as dt', 'dt.attribute_definition_id', '=', 'd.id')
            ->leftJoin('attribute_values as av', 'av.id', '=', 'pav.attribute_value_id')
            ->leftJoin('attribute_value_translations as vt', function ($join): void {
                $join->on('vt.attribute_value_id', '=', 'av.id')->on('vt.locale', '=', 'dt.locale');
            })
            ->whereIn('pav.product_id', $productIds)
            ->orderBy('pav.position')
            ->get(['pav.product_id', 'd.key', 'd.is_filterable', 'dt.locale', 'dt.name as definition_name', 'av.value_key', 'vt.name as value_name', 'pav.text_value', 'pav.number_value', 'pav.boolean_value']);

        return $this->groupAttributes($rows, 'product_id');
    }

    /** @return array<int, array<string, array<string, array<string, mixed>>>> */
    private function variantAttributes(array $variantIds): array
    {
        $rows = DB::table('variant_attribute_overrides as vao')
            ->join('attribute_definitions as d', 'd.id', '=', 'vao.attribute_definition_id')
            ->join('attribute_definition_translations as dt', 'dt.attribute_definition_id', '=', 'd.id')
            ->leftJoin('attribute_values as av', 'av.id', '=', 'vao.attribute_value_id')
            ->leftJoin('attribute_value_translations as vt', function ($join): void {
                $join->on('vt.attribute_value_id', '=', 'av.id')->on('vt.locale', '=', 'dt.locale');
            })
            ->whereIn('vao.product_variant_id', $variantIds)
            ->orderBy('vao.position')
            ->get(['vao.product_variant_id', 'd.key', 'd.is_filterable', 'dt.locale', 'dt.name as definition_name', 'av.value_key', 'vt.name as value_name', 'vao.text_value', 'vao.number_value', 'vao.boolean_value']);

        return $this->groupAttributes($rows, 'product_variant_id');
    }

    /**
     * @return array<int, array<string, array<string, non-empty-list<array{
     *     definitionKey: string,
     *     definitionName: string,
     *     definitionSlug: string,
     *     valueKey: string,
     *     valueName: string,
     *     valueSlug: string,
     *     filterable: bool
     * }>>>>
     */
    private function optionAttributes(array $variantIds): array
    {
        $rows = DB::table('variant_option_values as vov')
            ->join('product_options as po', 'po.id', '=', 'vov.product_option_id')
            ->join('attribute_definitions as d', 'd.id', '=', 'po.attribute_definition_id')
            ->join('attribute_definition_translations as dt', 'dt.attribute_definition_id', '=', 'd.id')
            ->join('attribute_values as av', 'av.id', '=', 'vov.attribute_value_id')
            ->join('attribute_value_translations as vt', function ($join): void {
                $join->on('vt.attribute_value_id', '=', 'av.id')->on('vt.locale', '=', 'dt.locale');
            })
            ->whereIn('vov.product_variant_id', $variantIds)
            ->orderBy('po.position')
            ->get(['vov.product_variant_id', 'd.key', 'd.is_filterable', 'dt.locale', 'dt.name as definition_name', 'dt.slug as definition_slug', 'av.value_key', 'vt.name as value_name', 'vt.slug as value_slug']);

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->product_variant_id][(string) $row->locale][(string) $row->key] = [[
                'definitionKey' => (string) $row->key,
                'definitionName' => (string) $row->definition_name,
                'definitionSlug' => (string) $row->definition_slug,
                'valueKey' => (string) $row->value_key,
                'valueName' => (string) $row->value_name,
                'valueSlug' => (string) $row->value_slug,
                'filterable' => (bool) $row->is_filterable,
            ]];
        }

        return $result;
    }

    private function groupAttributes(iterable $rows, string $ownerColumn): array
    {
        $result = [];
        foreach ($rows as $row) {
            $valueKey = $row->value_key ?? $row->text_value ?? $row->number_value;
            $valueName = $row->value_name ?? $row->text_value ?? $row->number_value;
            if ($valueKey === null && $row->boolean_value !== null) {
                $valueKey = $row->boolean_value ? '1' : '0';
                $valueName = $valueKey;
            }
            if ($valueKey === null) {
                continue;
            }
            $result[(int) $row->{$ownerColumn}][(string) $row->locale][(string) $row->key][] = [
                'definitionKey' => (string) $row->key,
                'definitionName' => (string) $row->definition_name,
                'valueKey' => (string) $valueKey,
                'valueName' => (string) $valueName,
                'filterable' => (bool) $row->is_filterable,
            ];
        }

        return $result;
    }

    private function effectiveAttributes(array $base, array $overrides, array $options): array
    {
        $groups = array_replace($base, $overrides, $options);

        return $groups === [] ? [] : array_merge(...array_values($groups));
    }
}
