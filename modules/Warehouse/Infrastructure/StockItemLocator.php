<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure;

use Illuminate\Database\QueryException;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockItem;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\Warehouse;

/**
 * Resolves and row-locks a variant's stock item within the caller's
 * transaction, creating it (0 on hand, backorder disabled) on first use.
 * Every reservation/adjustment mutation goes through this so concurrent
 * requests serialize on the same row instead of racing on insert.
 *
 * Must be called inside an open DB transaction — lockForUpdate() only
 * holds the lock until that transaction ends.
 */
final class StockItemLocator
{
    public function lockOrCreateForVariant(int $variantId): StockItem
    {
        $item = StockItem::query()->where('variant_id', $variantId)->lockForUpdate()->first();

        if ($item !== null) {
            return $item;
        }

        try {
            return StockItem::query()->create([
                'variant_id' => $variantId,
                'warehouse_id' => $this->defaultWarehouseId(),
                'on_hand_quantity' => '0',
                'reserved_quantity' => '0',
                'allow_backorder' => false,
            ]);
        } catch (QueryException) {
            // Lost a create race against a concurrent request for the same
            // variant — the row now exists, re-select it under lock.
            return StockItem::query()->where('variant_id', $variantId)->lockForUpdate()->firstOrFail();
        }
    }

    private function defaultWarehouseId(): int
    {
        $id = Warehouse::query()->value('id');

        if ($id === null) {
            throw new \RuntimeException('No warehouse exists yet — the installer must provision the default warehouse first.');
        }

        return (int) $id;
    }
}
