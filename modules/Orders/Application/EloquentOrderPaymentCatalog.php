<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\OrderPaymentCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentData;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;

final class EloquentOrderPaymentCatalog implements OrderPaymentCatalog
{
    public function __construct(private readonly OrderProjector $projector) {}

    public function find(string $orderId): ?OrderPaymentData
    {
        $order = Order::query()->find($orderId);

        return $order === null ? null : $this->projector->payment($order);
    }

    public function dueOrderIds(int $limit = 100): array
    {
        return Order::query()->where('payment_status', OrderPaymentStatus::Unpaid->value)
            ->whereNotNull('payment_deadline_at')->where('payment_deadline_at', '<=', now())
            ->orderBy('payment_deadline_at')->limit($limit)->pluck('id')->all();
    }
}
