<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockMovement;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Exercises StockAdjuster (receive/return/correct/backorder) end to end
 * through the public contract — the same object the /panel/warehouse
 * screen resolves.
 */
final class StockAdjusterTest extends TestCase
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

    private function createVariant(string $type = 'physical'): int
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => $type, 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => $type === 'digital' ? '1' : '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('product_variants')->value('id');
    }

    public function test_receiving_stock_creates_a_receipt_movement_and_increases_on_hand(): void
    {
        $variantId = $this->createVariant();

        app(StockAdjuster::class)->receive($variantId, '10', 'Dostawa od hurtownika', '1');

        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('10.000000', $item->onHandQuantity);
        $this->assertSame('10.000000', $item->availableQuantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'receipt', 'quantity_delta' => '10.000000', 'reason' => 'Dostawa od hurtownika']);
    }

    public function test_returning_stock_creates_a_return_movement(): void
    {
        $variantId = $this->createVariant();

        app(StockAdjuster::class)->returnToStock($variantId, '3', null, null);

        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('3.000000', $item->onHandQuantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'return', 'quantity_delta' => '3.000000']);
    }

    public function test_correcting_on_hand_records_only_the_difference(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);

        app(StockAdjuster::class)->correctOnHand($variantId, '7', 'Inwentaryzacja', '1');

        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('7.000000', $item->onHandQuantity);
        $this->assertDatabaseHas('stock_movements', ['type' => 'correction', 'quantity_delta' => '-3.000000', 'reason' => 'Inwentaryzacja']);
        // The receipt movement plus the correction — no phantom third movement.
        $this->assertSame(2, StockMovement::query()->count());
    }

    public function test_correcting_to_the_same_value_does_not_record_a_movement(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '5', null, null);

        app(StockAdjuster::class)->correctOnHand($variantId, '5', null, null);

        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_setting_allow_backorder_creates_the_stock_item_on_first_use(): void
    {
        $variantId = $this->createVariant();

        app(StockAdjuster::class)->setAllowBackorder($variantId, true);

        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertTrue($item->allowBackorder);
        $this->assertSame('0.000000', $item->onHandQuantity);
    }

    public function test_it_rejects_stock_operations_on_a_digital_variant(): void
    {
        $variantId = $this->createVariant('digital');

        $this->expectException(\InvalidArgumentException::class);
        app(StockAdjuster::class)->receive($variantId, '1', null, null);
    }

    public function test_it_rejects_a_non_positive_receive_quantity(): void
    {
        $variantId = $this->createVariant();

        $this->expectException(\InvalidArgumentException::class);
        app(StockAdjuster::class)->receive($variantId, '0', null, null);
    }
}
