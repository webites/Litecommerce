<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * VariantCatalog is Catalog's only cross-module contract (consumed by
 * Pricing/Inventory/Checkout, per modules/Catalog/Contracts/VariantCatalog.php).
 * Resolved through the container, like a real consumer would.
 */
final class VariantCatalogContractTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl',
            'name' => 'Polski',
            'is_default' => true,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_finds_a_variant_with_the_data_needed_by_other_modules(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload())->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->value('id');
        $variant = DB::table('product_variants')->where('product_id', $productId)->first();

        $found = app(VariantCatalog::class)->find((int) $variant->id);

        $this->assertNotNull($found);
        $this->assertSame((int) $variant->id, $found->id);
        $this->assertSame($productId, $found->productId);
        $this->assertSame('physical', $found->productType);
        $this->assertSame($variant->sku, $found->sku);
        $this->assertTrue($found->enabled);
        $this->assertSame('piece', $found->unitCode);
        $this->assertSame('1.000000', $found->minimumQuantity);
        $this->assertNull($found->maximumQuantity);
        $this->assertSame('1.000000', $found->quantityStep);
    }

    public function test_it_reflects_a_disabled_variant(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['sku' => 'DISABLE-ME']))->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->value('id');
        $variantId = (int) DB::table('product_variants')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.update', [$productId, $variantId]), [
            'locale' => 'pl',
            'sku' => 'DISABLE-ME',
            'enabled' => false,
        ])->assertSessionHasNoErrors();

        $found = app(VariantCatalog::class)->find($variantId);

        $this->assertNotNull($found);
        $this->assertFalse($found->enabled);
    }

    public function test_it_returns_null_for_an_unknown_variant(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff);

        $this->assertNull(app(VariantCatalog::class)->find(999999));
    }

    public function test_paginate_physical_lists_only_physical_variants_with_a_display_name(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['name' => 'Koszulka', 'sku' => 'SHIRT-1']))
            ->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['name' => 'E-book', 'type' => 'digital', 'sku' => 'EBOOK-1', 'maximum_quantity' => '1']))
            ->assertSessionHasNoErrors();

        $page = app(VariantCatalog::class)->paginatePhysical('pl', 25, 1);

        $this->assertSame(1, $page['total']);
        $this->assertCount(1, $page['items']);
        $this->assertSame('Koszulka', $page['items'][0]->displayName);
        $this->assertSame('SHIRT-1', $page['items'][0]->sku);
    }

    public function test_paginate_physical_pages_through_results(): void
    {
        $staff = StaffAccount::factory()->create();
        foreach (range(1, 3) as $i) {
            $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['name' => "Produkt {$i}", 'sku' => "SKU-{$i}"]))
                ->assertSessionHasNoErrors();
        }

        $page = app(VariantCatalog::class)->paginatePhysical('pl', 2, 1);
        $this->assertSame(3, $page['total']);
        $this->assertCount(2, $page['items']);

        $secondPage = app(VariantCatalog::class)->paginatePhysical('pl', 2, 2);
        $this->assertCount(1, $secondPage['items']);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'locale' => 'pl',
            'name' => 'Czerwona koszulka',
            'short_description' => 'Koszulka testowa',
            'description_html' => '',
            'type' => 'physical',
            'status' => 'draft',
            'featured' => false,
            'unit_code' => 'piece',
            'minimum_quantity' => '1',
            'maximum_quantity' => '',
            'quantity_step' => '1',
            'sku' => '',
            'gtin' => '',
            'mpn' => '',
            'brand_id' => '',
            'category_ids' => [],
            'primary_category_id' => '',
        ], $overrides);
    }
}
