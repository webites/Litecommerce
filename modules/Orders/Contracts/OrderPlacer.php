<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

interface OrderPlacer
{
    public function place(PlaceOrderData $data): PlacedOrderData;

    public function findBySource(string $sourceType, string $sourceId): ?PlacedOrderData;
}
