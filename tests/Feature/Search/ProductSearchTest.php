<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Search\Contracts\ProductSearchCriteria;
use Litecommerce\Modules\Search\Contracts\ProductSearchEngine;
use Litecommerce\Modules\Search\Contracts\SearchIndex;
use Tests\TestCase;

final class ProductSearchTest extends TestCase
{
    private int $warehouseId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--force' => true]);
        app()->setLocale('pl');
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->warehouseId = (int) DB::table('warehouses')->insertGetId(['name' => 'Główny', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_attribute_conditions_must_match_the_same_variant_and_hit_links_to_that_variant(): void
    {
        [$color, $colors] = $this->attribute('color', 'Kolor', ['red' => 'Czerwony', 'blue' => 'Niebieski']);
        [$size, $sizes] = $this->attribute('size', 'Rozmiar', ['s' => 'S', 'l' => 'L']);
        $split = $this->product('Rozdzielone opcje', 'split', [
            ['sku' => 'SPLIT-RS', 'options' => [$color => $colors['red'], $size => $sizes['s']]],
            ['sku' => 'SPLIT-BL', 'options' => [$color => $colors['blue'], $size => $sizes['l']]],
        ]);
        $matching = $this->product('Pasujący wariant', 'matching', [
            ['sku' => 'MATCH-RL', 'options' => [$color => $colors['red'], $size => $sizes['l']]],
        ]);

        app(SearchIndex::class)->rebuild();
        $result = app(ProductSearchEngine::class)->search($this->criteria(attributes: ['color' => ['red'], 'size' => ['l']]));

        $this->assertSame(1, $result->total);
        $this->assertSame($matching, $result->items[0]->product->id);
        $this->assertSame('color/red/size/l', $result->items[0]->variant->path);
        $this->assertNotSame($split, $result->items[0]->product->id);
    }

    public function test_search_ranks_available_products_before_unavailable_and_filters_effective_price(): void
    {
        $unavailable = $this->product('Dokładny produkt', 'exact', [['sku' => 'EXACT-SKU', 'stock' => 0, 'price' => '10.00']]);
        $available = $this->product('Inny produkt', 'available', [['sku' => 'OTHER', 'stock' => 5, 'price' => '25.00']], description: 'Pasuje do exact sku');

        app(SearchIndex::class)->rebuild();
        $ranked = app(ProductSearchEngine::class)->search($this->criteria(query: 'exact sku'));

        $this->assertSame([$available, $unavailable], array_map(static fn ($hit): int => $hit->product->id, $ranked->items));

        $priced = app(ProductSearchEngine::class)->search($this->criteria(minimumPrice: '20.00', maximumPrice: '30.00'));
        $this->assertSame(1, $priced->total);
        $this->assertSame($available, $priced->items[0]->product->id);
    }

    public function test_a_variant_without_a_price_is_ranked_as_unavailable_and_excluded_by_available_only(): void
    {
        $priceless = $this->product('Produkt bez ceny', 'priceless', [['sku' => 'NO-PRICE', 'stock' => 5, 'price' => null]]);
        $priced = $this->product('Produkt z ceną', 'priced', [['sku' => 'PRICED', 'stock' => 5, 'price' => '20.00']]);

        app(SearchIndex::class)->rebuild();
        $all = app(ProductSearchEngine::class)->search($this->criteria());

        $this->assertSame([$priced, $priceless], array_map(static fn ($hit): int => $hit->product->id, $all->items));
        $this->assertFalse($all->items[1]->variant->available);

        $availableOnly = app(ProductSearchEngine::class)->search($this->criteria(availableOnly: true));
        $this->assertSame(1, $availableOnly->total);
        $this->assertSame($priced, $availableOnly->items[0]->product->id);
    }

    public function test_polish_normalization_matches_a_basic_inflected_form(): void
    {
        $productId = $this->product('Czerwony fartuch', 'red-apron', [['sku' => 'APR-1', 'stock' => 2]]);
        app(SearchIndex::class)->rebuild();

        $result = app(ProductSearchEngine::class)->search($this->criteria(query: 'czerwona'));

        $this->assertSame(1, $result->total);
        $this->assertSame($productId, $result->items[0]->product->id);
    }

    public function test_each_variant_is_a_separate_paginated_result(): void
    {
        [$capacity, $capacities] = $this->attribute('capacity', 'Pojemność', [
            'small' => '250 ml',
            'medium' => '350 ml',
            'large' => '450 ml',
        ]);
        $productId = $this->product('Kubek Fjord', 'kubek-fjord', [
            ['sku' => 'MUG-250', 'options' => [$capacity => $capacities['small']]],
            ['sku' => 'MUG-350', 'options' => [$capacity => $capacities['medium']]],
            ['sku' => 'MUG-450', 'options' => [$capacity => $capacities['large']]],
        ]);

        app(SearchIndex::class)->rebuild();
        $firstPage = app(ProductSearchEngine::class)->search($this->criteria(perPage: 2));
        $secondPage = app(ProductSearchEngine::class)->search($this->criteria(page: 2, perPage: 2));

        $this->assertSame(3, $firstPage->total);
        $this->assertCount(2, $firstPage->items);
        $this->assertCount(1, $secondPage->items);
        $this->assertSame([$productId, $productId], array_map(static fn ($hit): int => $hit->product->id, $firstPage->items));
        $this->assertCount(3, array_unique(array_map(
            static fn ($hit): int => $hit->variant->variantId,
            [...$firstPage->items, ...$secondPage->items],
        )));
        $this->assertStringStartsWith('Kubek Fjord — ', $firstPage->items[0]->variant->name);
    }

    public function test_facets_only_include_attributes_present_in_the_selected_category(): void
    {
        [$capacity, $capacities] = $this->attribute('capacity', 'Pojemność', ['large' => '450 ml']);
        [$format, $formats] = $this->attribute('format', 'Format', ['a5' => 'A5']);
        $ceramics = $this->category('Ceramika', 'ceramika');
        $stationery = $this->category('Papiernicze', 'papiernicze');
        $mug = $this->product('Kubek', 'kubek', [['sku' => 'MUG', 'options' => [$capacity => $capacities['large']]]]);
        $notebook = $this->product('Notes', 'notes', [['sku' => 'NOTE', 'options' => [$format => $formats['a5']]]]);
        DB::table('product_categories')->insert([
            ['product_id' => $mug, 'category_id' => $ceramics],
            ['product_id' => $notebook, 'category_id' => $stationery],
        ]);

        app(SearchIndex::class)->rebuild();
        $result = app(ProductSearchEngine::class)->search($this->criteria(categoryId: $stationery));

        $this->assertSame(['format'], array_map(static fn ($filter): string => $filter->key, $result->filters));
        $this->assertSame(['a5'], array_map(static fn ($value): string => $value->key, $result->filters[0]->values));
    }

    public function test_warm_search_query_count_is_bounded_independently_of_page_size(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->product("Produkt {$i}", "produkt-{$i}", [['sku' => "SKU-{$i}", 'stock' => 2]]);
        }
        app(SearchIndex::class)->rebuild();

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(ProductSearchEngine::class)->search($this->criteria());
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(14, $queryCount, "Warm search used {$queryCount} queries.");
    }

    private function criteria(string $query = '', ?string $minimumPrice = null, ?string $maximumPrice = null, array $attributes = [], int $page = 1, int $perPage = 24, ?int $categoryId = null, bool $availableOnly = false): ProductSearchCriteria
    {
        return new ProductSearchCriteria($query, 'pl', 'PLN', $categoryId, $minimumPrice, $maximumPrice, $availableOnly, $attributes, $page, $perPage);
    }

    private function category(string $name, string $slug): int
    {
        $categoryId = (int) DB::table('categories')->insertGetId([
            'parent_id' => null, 'position' => 0, 'visible_in_tree' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('category_translations')->insert([
            'category_id' => $categoryId, 'locale' => 'pl', 'name' => $name, 'slug' => $slug,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $categoryId;
    }

    /** @return array{int, array<string, int>} */
    private function attribute(string $key, string $name, array $values): array
    {
        $definitionId = (int) DB::table('attribute_definitions')->insertGetId(['key' => $key, 'type' => 'select', 'is_filterable' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('attribute_definition_translations')->insert(['attribute_definition_id' => $definitionId, 'locale' => 'pl', 'name' => $name, 'slug' => $key, 'created_at' => now(), 'updated_at' => now()]);
        $ids = [];
        foreach ($values as $valueKey => $valueName) {
            $valueId = (int) DB::table('attribute_values')->insertGetId(['attribute_definition_id' => $definitionId, 'value_key' => $valueKey, 'position' => count($ids), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('attribute_value_translations')->insert(['attribute_value_id' => $valueId, 'locale' => 'pl', 'name' => $valueName, 'slug' => $valueKey, 'created_at' => now(), 'updated_at' => now()]);
            $ids[$valueKey] = $valueId;
        }

        return [$definitionId, $ids];
    }

    /** @param list<array{sku:string,options?:array<int,int>,stock?:int,price?:string|null}> $variants */
    private function product(string $name, string $slug, array $variants, string $description = ''): int
    {
        $productId = (int) DB::table('products')->insertGetId([
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => 1, 'quantity_step' => 1, 'publish_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_translations')->insert([
            'product_id' => $productId, 'locale' => 'pl', 'name' => $name, 'slug' => $slug,
            'short_description' => $description, 'description_html' => '', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $optionIds = [];
        foreach (array_keys($variants[0]['options'] ?? []) as $position => $definitionId) {
            $optionIds[$definitionId] = (int) DB::table('product_options')->insertGetId([
                'product_id' => $productId, 'attribute_definition_id' => $definitionId, 'position' => $position,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ($variants as $position => $variant) {
            $variantId = (int) DB::table('product_variants')->insertGetId([
                'product_id' => $productId, 'sku' => $variant['sku'], 'enabled' => true, 'is_default' => $position === 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($variant['options'] ?? [] as $definitionId => $valueId) {
                DB::table('variant_option_values')->insert(['product_variant_id' => $variantId, 'product_option_id' => $optionIds[$definitionId], 'attribute_value_id' => $valueId]);
            }
            if (! array_key_exists('price', $variant) || $variant['price'] !== null) {
                DB::table('variant_prices')->insert([
                    'variant_id' => $variantId, 'regular_amount' => $variant['price'] ?? '15.00', 'regular_mode' => 'gross',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('stock_items')->insert([
                'variant_id' => $variantId, 'warehouse_id' => $this->warehouseId, 'on_hand_quantity' => $variant['stock'] ?? 5,
                'reserved_quantity' => 0, 'allow_backorder' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $productId;
    }
}
