<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDelivery;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDeliveryEvent;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderOutboxEvent;

/** Appends one fulfillment fact to the delivery history and to the durable order outbox. */
final class DeliveryFulfillmentJournal
{
    public const READY_FOR_PICKUP = 'ready_for_pickup';

    public const HANDED_OVER = 'handed_over';

    public const PICKUP_EXPIRED = 'pickup_expired';

    public const PICKUP_REMINDER_SENT = 'pickup_reminder_sent';

    /** @param array<string, mixed> $data */
    public function record(Order $order, OrderDelivery $delivery, string $type, ?string $actorId, array $data = []): void
    {
        OrderDeliveryEvent::query()->create([
            'order_delivery_id' => $delivery->getKey(), 'order_id' => $order->id, 'type' => $type,
            'occurred_at' => now(), 'actor_type' => $actorId === null ? 'system' : 'staff', 'actor_id' => $actorId, 'data' => $data === [] ? null : $data,
        ]);
        OrderOutboxEvent::query()->create([
            'id' => (string) Str::uuid(), 'order_id' => $order->id, 'event_type' => Str::studly('order_'.$type),
            'aggregate_version' => $order->version, 'payload' => $data, 'occurred_at' => now(), 'available_at' => now(),
        ]);
    }
}
