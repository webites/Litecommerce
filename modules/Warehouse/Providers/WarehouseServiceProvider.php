<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Providers;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Warehouse\Application\AdjustStock;
use Litecommerce\Modules\Warehouse\Application\ManageStockReservations;
use Litecommerce\Modules\Warehouse\Application\WarehouseDemoDataContributor;
use Litecommerce\Modules\Warehouse\Contracts\Events\StockLevelChanged;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Litecommerce\Modules\Warehouse\Infrastructure\EloquentWarehouseProvisioner;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockItem;
use Litecommerce\Modules\Warehouse\Infrastructure\Repositories\EloquentStockLevelCatalog;

final class WarehouseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/warehouse.php', 'warehouse');

        $this->app->bind(WarehouseProvisioner::class, EloquentWarehouseProvisioner::class);
        $this->app->bind(StockLevelCatalog::class, EloquentStockLevelCatalog::class);
        $this->app->bind(StockAdjuster::class, AdjustStock::class);
        $this->app->bind(StockReservationManager::class, ManageStockReservations::class);
        $this->app->singleton(WarehouseDemoDataContributor::class);
        $this->app->tag(WarehouseDemoDataContributor::class, DemoDataContributor::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'warehouse');

        $this->app->make(ScheduledTaskRegistry::class)->register(
            'core.warehouse.reconcile_expired_reservations',
            60,
            fn () => $this->app->make(StockReservationManager::class)->reconcileExpiredReservations(
                null,
                (int) config('warehouse.reconcile_batch', 200),
            ),
        );

        StockItem::saved(static fn (StockItem $item) => DB::afterCommit(fn () => CommittedEvent::dispatch(new StockLevelChanged([(int) $item->variant_id]))));
        StockItem::deleted(static fn (StockItem $item) => DB::afterCommit(fn () => CommittedEvent::dispatch(new StockLevelChanged([(int) $item->variant_id]))));
    }
}
