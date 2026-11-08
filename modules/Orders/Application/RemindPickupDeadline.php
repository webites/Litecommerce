<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState;
use Litecommerce\Modules\Orders\Contracts\OrderStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDelivery;

/** Emails the customer once, shortly before the pickup deadline of an order that is still waiting. */
final class RemindPickupDeadline
{
    public function __construct(
        private readonly DeliveryFulfillmentJournal $journal,
        private readonly OrderEmailComposer $emails,
    ) {}

    public function execute(string $orderId, \DateTimeImmutable $now): bool
    {
        return DB::transaction(function () use ($orderId, $now): bool {
            $locked = LockedPickupOrder::find($orderId);

            if ($locked === null || ! $this->isDue($locked, $now)) {
                return false;
            }

            $this->emails->pickupReminder($locked->order, $locked->delivery, $locked->isPayOnPickup());
            $locked->delivery->update(['pickup_reminder_sent_at' => $now]);
            $this->journal->record($locked->order, $locked->delivery, DeliveryFulfillmentJournal::PICKUP_REMINDER_SENT, null);

            return true;
        });
    }

    /** @return list<string> */
    public function dueOrderIds(\DateTimeImmutable $now, int $limit): array
    {
        return OrderDelivery::query()
            ->where('fulfillment_state', DeliveryFulfillmentState::ReadyForPickup->value)
            ->whereNull('pickup_reminder_sent_at')
            ->where('pickup_reminder_at', '<=', $now)
            ->where('pickup_deadline_at', '>', $now)
            ->orderBy('pickup_reminder_at')->limit($limit)->pluck('order_id')->all();
    }

    private function isDue(LockedPickupOrder $locked, \DateTimeImmutable $now): bool
    {
        $delivery = $locked->delivery;

        return $delivery->fulfillment_state === DeliveryFulfillmentState::ReadyForPickup->value
            && $delivery->pickup_reminder_sent_at === null
            && $delivery->pickup_reminder_at !== null && $delivery->pickup_reminder_at <= $now
            && $delivery->pickup_deadline_at !== null && $delivery->pickup_deadline_at > $now
            && ! in_array($locked->order->status, [OrderStatus::Cancelled->value, OrderStatus::PaymentReview->value], true);
    }
}
