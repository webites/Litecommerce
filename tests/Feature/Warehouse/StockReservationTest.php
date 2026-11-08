<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Litecommerce\Modules\Warehouse\Contracts\InsufficientStockException;
use Litecommerce\Modules\Warehouse\Contracts\InvalidReservationStateException;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationState;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockItem;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockMovement;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockReservation;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Exercises the reservation state machine in
 * docs/knowledge-base/06-fulfillment.md and 05-orders.md — the same
 * StockReservationManager contract the future Orders module will resolve.
 */
final class StockReservationTest extends TestCase
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

    private function createVariant(string $name = 'Kubek testowy'): int
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('product_variants')->orderByDesc('id')->value('id');
    }

    public function test_reserving_increases_reserved_and_reduces_available(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);

        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);

        $this->assertSame(StockReservationState::Pending, $reservation->state);
        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('4.000000', $item->reservedQuantity);
        $this->assertSame('6.000000', $item->availableQuantity);
    }

    public function test_reserving_more_than_available_without_backorder_throws(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '2', null, null);

        $this->expectException(InsufficientStockException::class);
        app(StockReservationManager::class)->reserve($variantId, '3', 'order_line', 'ol-1', null);
    }

    public function test_backorder_allows_reserving_past_available_stock(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '2', null, null);
        app(StockAdjuster::class)->setAllowBackorder($variantId, true);

        $reservation = app(StockReservationManager::class)->reserve($variantId, '5', 'order_line', 'ol-1', null);

        $this->assertSame('5.000000', $reservation->quantity);
        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('5.000000', $item->reservedQuantity);
        $this->assertSame('-3.000000', $item->availableQuantity);
    }

    public function test_confirm_makes_the_reservation_indefinite(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', new \DateTimeImmutable('+60 minutes'));

        app(StockReservationManager::class)->confirm($reservation->id);

        $row = StockReservation::query()->findOrFail($reservation->id);
        $this->assertSame(StockReservationState::Confirmed->value, $row->state);
        $this->assertNull($row->expires_at);
    }

    public function test_confirm_is_idempotent(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);

        app(StockReservationManager::class)->confirm($reservation->id);
        app(StockReservationManager::class)->confirm($reservation->id);

        $this->assertSame(StockReservationState::Confirmed->value, StockReservation::query()->findOrFail($reservation->id)->state);
    }

    public function test_release_frees_reserved_quantity_without_touching_on_hand(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);

        app(StockReservationManager::class)->release($reservation->id);

        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('0.000000', $item->reservedQuantity);
        $this->assertSame('10.000000', $item->onHandQuantity);
        $this->assertSame(StockReservationState::Released->value, StockReservation::query()->findOrFail($reservation->id)->state);
    }

    public function test_release_is_idempotent(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);

        app(StockReservationManager::class)->release($reservation->id);
        app(StockReservationManager::class)->release($reservation->id);

        $this->assertSame('0.000000', app(StockLevelCatalog::class)->find($variantId)->reservedQuantity);
    }

    public function test_fulfill_atomically_settles_the_reservation_and_decreases_on_hand(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);
        app(StockReservationManager::class)->confirm($reservation->id);

        app(StockReservationManager::class)->fulfill($reservation->id, '1');

        $item = app(StockLevelCatalog::class)->find($variantId);
        $this->assertSame('6.000000', $item->onHandQuantity);
        $this->assertSame('0.000000', $item->reservedQuantity);
        $this->assertSame(StockReservationState::Fulfilled->value, StockReservation::query()->findOrFail($reservation->id)->state);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'issue', 'quantity_delta' => '-4.000000', 'reference_type' => 'stock_reservation', 'reference_id' => (string) $reservation->id,
        ]);
    }

    public function test_fulfill_is_idempotent_and_does_not_double_decrease_on_hand(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);

        app(StockReservationManager::class)->fulfill($reservation->id);
        app(StockReservationManager::class)->fulfill($reservation->id);

        $this->assertSame('6.000000', app(StockLevelCatalog::class)->find($variantId)->onHandQuantity);
        $this->assertSame(1, StockMovement::query()->where('type', 'issue')->count());
    }

    public function test_fulfilling_a_released_reservation_is_rejected(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', null);
        app(StockReservationManager::class)->release($reservation->id);

        try {
            app(StockReservationManager::class)->fulfill($reservation->id);
            $this->fail('Expected InvalidReservationStateException.');
        } catch (InvalidReservationStateException $e) {
            $this->assertSame(StockReservationState::Released, $e->currentState);
            $this->assertSame('fulfill', $e->attemptedTransition);
        }

        // The stock the released hold no longer covers must not be
        // decreased — it may have already been sold to someone else.
        $this->assertSame('10.000000', app(StockLevelCatalog::class)->find($variantId)->onHandQuantity);
    }

    public function test_confirming_an_expired_reservation_signals_a_late_payment_situation(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $reservation = app(StockReservationManager::class)->reserve($variantId, '4', 'order_line', 'ol-1', new \DateTimeImmutable('-1 minute'));
        // Someone else's reserve() call on the same variant opportunistically
        // reconciles the expired hold before the late confirmation arrives.
        app(StockReservationManager::class)->reserve($variantId, '1', 'order_line', 'ol-2', null);

        try {
            app(StockReservationManager::class)->confirm($reservation->id);
            $this->fail('Expected InvalidReservationStateException.');
        } catch (InvalidReservationStateException $e) {
            $this->assertSame(StockReservationState::Expired, $e->currentState);
            $this->assertSame($reservation->id, $e->reservationId);
        }
    }

    public function test_reconcile_releases_only_reservations_past_their_expiry(): void
    {
        // Built directly (not via reserve()) so the still-valid hold below
        // isn't opportunistically reconciled as a side effect of creating
        // it — see the dedicated opportunistic-reconciliation test instead.
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '10', null, null);
        $item = StockItem::query()->where('variant_id', $variantId)->firstOrFail();
        $expired = StockReservation::query()->create([
            'stock_item_id' => $item->id, 'quantity' => '3', 'state' => StockReservationState::Pending->value,
            'reference_type' => 'order_line', 'reference_id' => 'ol-1', 'expires_at' => now()->subMinute(),
        ]);
        $stillValid = StockReservation::query()->create([
            'stock_item_id' => $item->id, 'quantity' => '2', 'state' => StockReservationState::Pending->value,
            'reference_type' => 'order_line', 'reference_id' => 'ol-2', 'expires_at' => now()->addHour(),
        ]);
        $item->update(['reserved_quantity' => '5']);

        $released = app(StockReservationManager::class)->reconcileExpiredReservations($variantId);

        $this->assertSame(1, $released);
        $this->assertSame(StockReservationState::Expired->value, StockReservation::query()->findOrFail($expired->id)->state);
        $this->assertSame(StockReservationState::Pending->value, StockReservation::query()->findOrFail($stillValid->id)->state);
        // Only the expired 3 were freed — the still-valid 2 remain reserved.
        $this->assertSame('2.000000', app(StockLevelCatalog::class)->find($variantId)->reservedQuantity);
    }

    public function test_global_sweep_honours_its_limit_and_the_scheduler_task_drains_the_rest(): void
    {
        $items = [];
        foreach ([1, 2] as $n) {
            $variantId = $this->createVariant("Produkt {$n}");
            app(StockAdjuster::class)->receive($variantId, '10', null, null);
            $item = StockItem::query()->where('variant_id', $variantId)->firstOrFail();
            StockReservation::query()->create([
                'stock_item_id' => $item->id, 'quantity' => '3', 'state' => StockReservationState::Pending->value,
                'reference_type' => 'order_line', 'reference_id' => "ol-{$n}", 'expires_at' => now()->subMinute(),
            ]);
            $item->update(['reserved_quantity' => '3']);
            $items[] = $item->id;
        }

        $this->assertSame(1, app(StockReservationManager::class)->reconcileExpiredReservations(null, 1));
        $this->assertSame(1, StockReservation::query()->where('state', StockReservationState::Pending->value)->count());

        app(RunDueScheduledTasks::class)->run(20, 10);

        $this->assertSame(0, StockReservation::query()->where('state', StockReservationState::Pending->value)->count());
        $this->assertSame(0.0, (float) StockItem::query()->whereIn('id', $items)->sum('reserved_quantity'));
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.warehouse.reconcile_expired_reservations', 'run_count' => 1, 'consecutive_failures' => 0]);
    }

    public function test_reserve_opportunistically_reconciles_this_variants_expired_reservations_first(): void
    {
        $variantId = $this->createVariant();
        app(StockAdjuster::class)->receive($variantId, '5', null, null);
        app(StockReservationManager::class)->reserve($variantId, '5', 'order_line', 'ol-1', new \DateTimeImmutable('-1 minute'));

        // Without reconciling the expired hold above, this would exceed
        // available stock (0 available) and throw.
        $reservation = app(StockReservationManager::class)->reserve($variantId, '5', 'order_line', 'ol-2', null);

        $this->assertSame('5.000000', $reservation->quantity);
    }
}
