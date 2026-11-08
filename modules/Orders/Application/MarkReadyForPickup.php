<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState;
use Litecommerce\Modules\Orders\Contracts\Events\OrderReadyForPickup;
use Litecommerce\Modules\Orders\Contracts\InvalidFulfillmentTransitionException as Invalid;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;

final class MarkReadyForPickup
{
    public function __construct(
        private readonly DeliveryFulfillmentJournal $journal,
        private readonly OrderEmailComposer $emails,
    ) {}

    public function execute(string $orderId, string $actorId, ?\DateTimeImmutable $deadline): bool
    {
        return DB::transaction(function () use ($orderId, $actorId, $deadline): bool {
            $locked = LockedPickupOrder::require($orderId);
            $order = $locked->order;
            $delivery = $locked->delivery;
            $locked->assertFulfillable();

            if ($delivery->fulfillment_state === DeliveryFulfillmentState::ReadyForPickup->value) {
                return false;
            }

            if ($delivery->fulfillment_state !== DeliveryFulfillmentState::Pending->value) {
                throw new Invalid(Invalid::WRONG_STATE, 'To zamówienie zostało już wydane albo wygasło.');
            }

            $paid = in_array($order->payment_status, [OrderPaymentStatus::Paid->value, OrderPaymentStatus::Overpaid->value], true);

            if (! $paid && ! ($locked->isPayOnPickup() && $order->payment_status === OrderPaymentStatus::Unpaid->value)) {
                throw new Invalid(Invalid::PAYMENT_REQUIRED, 'Zamówienie wymaga potwierdzenia wpłaty przed przygotowaniem do odbioru.');
            }

            $readyAt = now();

            if ($deadline !== null && $deadline <= $readyAt) {
                throw new Invalid(Invalid::DEADLINE_IN_PAST, 'Termin odbioru musi być w przyszłości.');
            }

            $order->update(['version' => $order->version + 1]);
            $delivery->update([
                'fulfillment_state' => DeliveryFulfillmentState::ReadyForPickup->value,
                'ready_for_pickup_at' => $readyAt, 'pickup_deadline_at' => $deadline,
                'pickup_reminder_at' => $this->reminderAt($deadline, $readyAt),
            ]);
            $this->journal->record($order, $delivery, DeliveryFulfillmentJournal::READY_FOR_PICKUP, $actorId, [
                'deadline_at' => $deadline?->format(DATE_ATOM),
            ]);
            $this->emails->readyForPickup($order, $delivery);

            $event = new OrderReadyForPickup($order->id, \DateTimeImmutable::createFromInterface($readyAt), $deadline);
            DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

            return true;
        });
    }

    private function reminderAt(?\DateTimeImmutable $deadline, \DateTimeInterface $readyAt): ?\DateTimeImmutable
    {
        $days = (int) config('orders.pickup_reminder_days', 2);

        if ($deadline === null || $days < 1) {
            return null;
        }

        $at = $deadline->modify("-{$days} days");

        return $at > $readyAt ? $at : null;
    }
}
