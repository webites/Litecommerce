<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState;
use Litecommerce\Modules\Orders\Contracts\Events\OrderHandedOver;
use Litecommerce\Modules\Orders\Contracts\InvalidFulfillmentTransitionException as Invalid;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Contracts\PickupPaymentRecorder;
use Litecommerce\Modules\Warehouse\Contracts\InvalidReservationStateException;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;

final class ConfirmHandover
{
    public function __construct(
        private readonly DeliveryFulfillmentJournal $journal,
        private readonly PickupPaymentRecorder $payments,
        private readonly StockReservationManager $stock,
    ) {}

    public function execute(string $orderId, string $actorId, ?string $paymentReference, ?\DateTimeImmutable $paidAt): bool
    {
        return DB::transaction(function () use ($orderId, $actorId, $paymentReference, $paidAt): bool {
            $locked = LockedPickupOrder::require($orderId);
            $order = $locked->order;
            $delivery = $locked->delivery;
            $locked->assertFulfillable();

            if ($delivery->fulfillment_state === DeliveryFulfillmentState::HandedOver->value) {
                return false;
            }

            if ($delivery->fulfillment_state !== DeliveryFulfillmentState::ReadyForPickup->value) {
                throw new Invalid(Invalid::WRONG_STATE, 'Zamówienie musi najpierw być oznaczone jako gotowe do odbioru.');
            }

            $handedOverAt = now();
            $paymentRecorded = false;

            if ($order->payment_status === OrderPaymentStatus::Unpaid->value) {
                if (! $locked->isPayOnPickup() || $paymentReference === null || trim($paymentReference) === '') {
                    throw new Invalid(Invalid::PAYMENT_REQUIRED, 'Wydanie zamówienia płatnego na miejscu wymaga potwierdzenia otrzymania wpłaty.');
                }

                $this->payments->recordReceivedOnPickup($orderId, $paymentReference, $paidAt ?? \DateTimeImmutable::createFromInterface($handedOverAt), $actorId);
                $order->refresh();
                $paymentRecorded = true;
            }

            if ($order->payment_status === OrderPaymentStatus::LatePayment->value && $paymentRecorded) {
                throw new Invalid(Invalid::STOCK_UNAVAILABLE, 'Rezerwacja towaru wygasła lub została zwolniona; sprawa wymaga decyzji pracownika.');
            }

            if (! in_array($order->payment_status, [OrderPaymentStatus::Paid->value, OrderPaymentStatus::Overpaid->value], true)) {
                throw new Invalid(Invalid::PAYMENT_REQUIRED, 'Zamówienie nie jest opłacone.');
            }

            try {
                foreach ($order->lines as $line) {
                    if ($line->stock_reservation_id !== null) {
                        $this->stock->fulfill((int) $line->stock_reservation_id, $actorId);
                    }
                }
            } catch (InvalidReservationStateException) {
                throw new Invalid(Invalid::STOCK_UNAVAILABLE, 'Rezerwacja towaru wygasła lub została zwolniona; sprawa wymaga decyzji pracownika.');
            }

            $order->update(['version' => $order->version + 1]);
            $delivery->update(['fulfillment_state' => DeliveryFulfillmentState::HandedOver->value, 'handed_over_at' => $handedOverAt]);
            $this->journal->record($order, $delivery, DeliveryFulfillmentJournal::HANDED_OVER, $actorId, [
                'payment_recorded' => $paymentRecorded,
            ]);

            $event = new OrderHandedOver($order->id, \DateTimeImmutable::createFromInterface($handedOverAt));
            DB::afterCommit(static fn () => CommittedEvent::dispatch($event));

            return true;
        });
    }
}
