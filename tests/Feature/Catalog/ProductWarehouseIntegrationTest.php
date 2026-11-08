<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Catalog's product edit page embeds a read-only stock summary per
 * variant, exactly like it already embeds Pricing data — this covers
 * that wiring end to end through the real HTTP route.
 */
final class ProductWarehouseIntegrationTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function createProductAndVariant(StaffAccount $staff, string $type): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => $type, 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => $type === 'digital' ? '1' : '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->value('id');
        $variantId = (int) DB::table('product_variants')->value('id');

        return [$productId, $variantId];
    }

    public function test_it_displays_stock_for_a_physical_variant(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createProductAndVariant($staff, 'physical');
        app(StockAdjuster::class)->receive($variantId, '8', null, null);

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Catalog/Edit')
                ->where('variants.0.stock.on_hand_quantity', '8')
                ->where('variants.0.stock.available_quantity', '8')
                ->where('variants.0.stock.allow_backorder', false));
    }

    public function test_it_omits_stock_for_a_digital_variant(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId] = $this->createProductAndVariant($staff, 'digital');

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Catalog/Edit')
                ->where('variants.0.stock', null));
    }
}
