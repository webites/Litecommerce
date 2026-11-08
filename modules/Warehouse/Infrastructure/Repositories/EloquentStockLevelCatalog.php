<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure\Repositories;

use Litecommerce\Modules\Warehouse\Contracts\StockItemData;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;
use Litecommerce\Modules\Warehouse\Domain\Quantity;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockItem;

final class EloquentStockLevelCatalog implements StockLevelCatalog
{
    public function find(int $variantId): ?StockItemData
    {
        $item = StockItem::query()->where('variant_id', $variantId)->first();

        return $item === null ? null : $this->toData($item);
    }

    public function findMany(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $items = StockItem::query()->whereIn('variant_id', $variantIds)->get();
        $result = [];

        foreach ($items as $item) {
            $result[$item->variant_id] = $this->toData($item);
        }

        return $result;
    }

    private function toData(StockItem $item): StockItemData
    {
        return new StockItemData(
            variantId: $item->variant_id,
            warehouseId: $item->warehouse_id,
            onHandQuantity: $item->on_hand_quantity,
            reservedQuantity: $item->reserved_quantity,
            availableQuantity: Quantity::sub($item->on_hand_quantity, $item->reserved_quantity),
            allowBackorder: $item->allow_backorder,
        );
    }
}
