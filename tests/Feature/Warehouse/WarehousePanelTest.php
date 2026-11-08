<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * HTTP-level coverage for the /panel/warehouse screen — the cross-product
 * stock list plus receive/correct/backorder actions — and the
 * ManageInventory permission gate.
 */
final class WarehousePanelTest extends TestCase
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

    private function createVariant(StaffAccount $staff, string $name = 'Kubek testowy', string $type = 'physical'): int
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '',
            'type' => $type, 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => $type === 'digital' ? '1' : '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('product_variants')->latest('id')->value('id');
    }

    public function test_index_lists_physical_variants_with_stock_and_omits_digital_ones(): void
    {
        $staff = StaffAccount::factory()->create();
        $physical = $this->createVariant($staff, 'Kubek testowy', 'physical');
        $this->createVariant($staff, 'E-book testowy', 'digital');

        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $physical), ['quantity' => '5'])
            ->assertSessionHasNoErrors();

        $this->actingAs($staff)->get(route('panel.warehouse.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Panel/Warehouse/Index')
                ->has('items.data', 1)
                ->where('items.data.0.on_hand_quantity', '5')
                ->where('items.data.0.available_quantity', '5'));
    }

    public function test_receiving_and_correcting_stock_through_the_panel(): void
    {
        $staff = StaffAccount::factory()->create();
        $variantId = $this->createVariant($staff);

        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), [
            'quantity' => '12', 'reason' => 'Dostawa',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_items', ['variant_id' => $variantId, 'on_hand_quantity' => 12]);

        $this->actingAs($staff)->put(route('panel.warehouse.variants.correct', $variantId), [
            'on_hand_quantity' => '9', 'reason' => 'Inwentaryzacja',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_items', ['variant_id' => $variantId, 'on_hand_quantity' => 9]);
        $this->assertDatabaseHas('stock_movements', ['type' => 'correction', 'quantity_delta' => -3]);
    }

    public function test_toggling_backorder_through_the_panel(): void
    {
        $staff = StaffAccount::factory()->create();
        $variantId = $this->createVariant($staff);

        $this->actingAs($staff)->put(route('panel.warehouse.variants.backorder.update', $variantId), [
            'allow_backorder' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stock_items', ['variant_id' => $variantId, 'allow_backorder' => true]);
    }

    public function test_a_content_editor_without_manage_inventory_is_forbidden(): void
    {
        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();

        $this->actingAs($editor)->get(route('panel.warehouse.index'))->assertForbidden();
    }

    public function test_a_warehouse_worker_can_access_the_screen(): void
    {
        $worker = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($worker)->get(route('panel.warehouse.index'))->assertOk();
    }

    public function test_receiving_stock_for_an_unknown_variant_is_a_clean_404(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', 999999), ['quantity' => '1'])
            ->assertNotFound();
    }

    public function test_receiving_stock_for_a_digital_variant_is_a_clean_404(): void
    {
        $staff = StaffAccount::factory()->create();
        $digitalVariantId = $this->createVariant($staff, 'E-book testowy', 'digital');

        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $digitalVariantId), ['quantity' => '1'])
            ->assertNotFound();
    }
}
