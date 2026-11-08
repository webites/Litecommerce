<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Orders\Contracts\Events\OrderStatusChanged;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderNote;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderOutboxEvent;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderStatusHistory;

final class ManageOrder
{
    public function __construct(
        private readonly OrderStatusRegistry $statuses,
        private readonly OrderEmailComposer $emails,
    ) {}

    public function changeStatus(string $orderId, string $newStatus, string $actorId, ?string $reason): void
    {
        if (! $this->statuses->has($newStatus) || $newStatus === 'cancelled') {
            throw new \InvalidArgumentException('Nieprawidłowy status. Anulowanie wymaga osobnej operacji biznesowej.');
        }
        DB::transaction(function () use ($orderId, $newStatus, $actorId, $reason): void {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            if ($order->status === $newStatus) {
                return;
            }
            $old = $order->status;
            $order->update(['status' => $newStatus, 'version' => $order->version + 1]);
            OrderStatusHistory::query()->create([
                'order_id' => $order->id, 'old_status' => $old, 'new_status' => $newStatus,
                'actor_type' => 'staff', 'actor_id' => $actorId, 'reason' => $reason, 'occurred_at' => now(),
            ]);
            OrderOutboxEvent::query()->create([
                'id' => (string) Str::uuid(), 'order_id' => $order->id, 'event_type' => 'OrderStatusChanged',
                'aggregate_version' => $order->version,
                'payload' => ['old_status' => $old, 'new_status' => $newStatus, 'actor_id' => $actorId, 'reason' => $reason],
                'occurred_at' => now(), 'available_at' => now(),
            ]);
            $this->emails->statusChanged($order, $old, $newStatus, $reason);
            DB::afterCommit(fn () => CommittedEvent::dispatch(new OrderStatusChanged($orderId, $newStatus)));
        });
    }

    public function addNote(string $orderId, string $body, string $actorId): void
    {
        Order::query()->findOrFail($orderId);
        OrderNote::query()->create(['order_id' => $orderId, 'body' => trim($body), 'author_type' => 'staff', 'author_id' => $actorId]);
    }

    public function sendCustomerMessage(string $orderId, string $body, string $actorId): void
    {
        DB::transaction(function () use ($orderId, $body, $actorId): void {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            $this->emails->manualMessage($order, $body, $actorId, (string) Str::uuid());
        });
    }
}
