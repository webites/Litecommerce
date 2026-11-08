<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure;

use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\Warehouse;

final class EloquentWarehouseProvisioner implements WarehouseProvisioner
{
    public function ensureDefaultWarehouseExists(): void
    {
        if (Warehouse::query()->exists()) {
            return;
        }

        Warehouse::query()->create([
            'name' => config('warehouse.default_name', 'Magazyn główny'),
        ]);
    }
}
