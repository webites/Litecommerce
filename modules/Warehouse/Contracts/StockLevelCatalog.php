<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

interface StockLevelCatalog
{
    public function find(int $variantId): ?StockItemData;

    /**
     * Bulk read for listings — never resolve a listing's stock levels with
     * one find() call per row.
     *
     * @param  list<int>  $variantIds
     * @return array<int, StockItemData> keyed by variant id; variants
     *                                   without a stock item yet are omitted
     */
    public function findMany(array $variantIds): array;
}
