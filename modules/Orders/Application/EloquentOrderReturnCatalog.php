<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\OrderReturnableData;
use Litecommerce\Modules\Orders\Contracts\OrderReturnCatalog;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;

final class EloquentOrderReturnCatalog implements OrderReturnCatalog
{
    public function __construct(private readonly OrderProjector $projector) {}

    public function find(string $orderId): ?OrderReturnableData
    {
        $order = Order::query()->with(['lines', 'delivery'])->find($orderId);

        return $order === null ? null : $this->projector->returnable($order);
    }

    public function numbersFor(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return Order::query()->whereIn('id', $orderIds)->pluck('public_number', 'id')->all();
    }
}
