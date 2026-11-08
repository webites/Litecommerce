<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

final readonly class StockItemData
{
    public function __construct(
        public int $variantId,
        public int $warehouseId,
        public string $onHandQuantity,
        public string $reservedQuantity,
        public string $availableQuantity,
        public bool $allowBackorder,
    ) {}
}
