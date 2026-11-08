<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState;
use Litecommerce\Modules\Orders\Contracts\Events\OrderPickupExpired;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Contracts\OrderStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDelivery;

/**
 * Cancels an unpaid pay-on-pickup order whose pickup deadline lapsed and
 * releases its stock and coupons. A prepaid order never lapses on its own.
 */
final class ExpireUncollected
{
    public function __construct(
        private readonly DeliveryFulfillmentJournal $journal,
        private readonly OrderPaymentManager $payments,
        private readonly OrderEmailComposer $emails,
    ) {}

    public function execute(string $orderId, \DateTimeImmutable $now): bool
    {
        return DB::transaction(function () use ($orderId, $now): bool {
            $locked = LockedPickupOrder::find($orderId);

            if ($locked === null || ! $this->isDue($locked, $now)) {
                return false;
            }

            $this->payments->cancelUnpaid($orderId, 'Nieodebrane w terminie.');
            $order = $locked->order->refresh();
            $locked->delivery->update(['fulfillment_state' => DeliveryFulfillmentState::PickupExpired->value]);
            $this->journal->record($order, $locked->delivery, DeliveryFulfillmentJournal::PICKUP_EXPIRED, null, [
                'deadline_at' => $locked->delivery->pickup_deadline_at?->toAtomString(),
            ]);

            $this->emails->pickupExpired($order, $locked->delivery);

            $event = new OrderPickupExpired($order->id, $now);
            DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

            return true;
        });
    }

    /** @return list<string> */
    public function dueOrderIds(\DateTimeImmutable $now, int $limit): array
    {
        return OrderDelivery::query()
            ->where('fulfillment_state', DeliveryFulfillmentState::ReadyForPickup->value)
            ->where('pickup_deadline_at', '<=', $now)
            ->whereIn('order_id', Order::query()->select('id')
                ->where('payment_kind', 'pay_on_pickup')->where('payment_status', OrderPaymentStatus::Unpaid->value)
                ->where('status', '!=', OrderStatus::Cancelled->value))
            ->orderBy('pickup_deadline_at')->limit($limit)->pluck('order_id')->all();
    }

    private function isDue(LockedPickupOrder $locked, \DateTimeImmutable $now): bool
    {
        $delivery = $locked->delivery;

        return $delivery->fulfillment_state === DeliveryFulfillmentState::ReadyForPickup->value
            && $delivery->pickup_deadline_at !== null && $delivery->pickup_deadline_at <= $now
            && $locked->isPayOnPickup()
            && $locked->order->payment_status === OrderPaymentStatus::Unpaid->value
            && $locked->order->status !== OrderStatus::Cancelled->value;
    }
}
