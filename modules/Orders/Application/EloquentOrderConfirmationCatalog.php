<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\OrderConfirmationCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderDetailData;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;

final class EloquentOrderConfirmationCatalog implements OrderConfirmationCatalog
{
    public function __construct(private readonly OrderProjector $projector) {}

    public function find(string $orderId): ?OrderDetailData
    {
        $order = Order::query()->with(['party', 'delivery', 'lines'])->find($orderId);

        return $order === null ? null : $this->projector->detail($order);
    }
}
