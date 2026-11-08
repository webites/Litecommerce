<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Presentation;

use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Contracts\OrderStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;

final readonly class PickupFulfillmentPresenter
{
    public function __construct(private DeliveryMethodCatalog $deliveryMethods) {}

    /** @return array<string, mixed>|null */
    public function present(Order $order): ?array
    {
        $delivery = $order->delivery;

        if ($delivery === null || $delivery->kind !== 'pickup') {
            return null;
        }

        $payOnPickup = $order->payment_kind === 'pay_on_pickup';
        $unpaid = $order->payment_status === OrderPaymentStatus::Unpaid->value;
        $open = $delivery->fulfillment_state === DeliveryFulfillmentState::Pending->value || $delivery->fulfillment_state === DeliveryFulfillmentState::ReadyForPickup->value;
        $actionable = $open && ! in_array($order->status, [OrderStatus::Cancelled->value, OrderStatus::PaymentReview->value], true);

        return [
            'state' => $delivery->fulfillment_state,
            'ready_for_pickup_at' => $delivery->ready_for_pickup_at?->toIso8601String(),
            'pickup_deadline_at' => $delivery->pickup_deadline_at?->toIso8601String(),
            'handed_over_at' => $delivery->handed_over_at?->toIso8601String(),
            'pay_on_pickup' => $payOnPickup,
            'requires_payment_on_handover' => $payOnPickup && $unpaid,
            'can_mark_ready' => $actionable && $delivery->fulfillment_state === DeliveryFulfillmentState::Pending->value && ($payOnPickup || ! $unpaid),
            'can_hand_over' => $actionable && $delivery->fulfillment_state === DeliveryFulfillmentState::ReadyForPickup->value,
            'default_pickup_deadline_at' => $this->defaultDeadline($order, (string) $delivery->delivery_method_id, $payOnPickup),
            'events' => $delivery->events()->orderByDesc('occurred_at')->orderByDesc('id')->get()
                ->map(static fn ($event): array => [
                    'type' => $event->type, 'occurred_at' => $event->occurred_at->toIso8601String(),
                    'actor_type' => $event->actor_type, 'actor_id' => $event->actor_id,
                ])->all(),
        ];
    }

    private function defaultDeadline(Order $order, string $methodId, bool $payOnPickup): ?string
    {
        if (! $payOnPickup || $methodId === '') {
            return null;
        }

        $days = $this->deliveryMethods->pickupDeadlineDays($methodId);

        return $days === null ? null : now()->addDays($days)->toIso8601String();
    }
}
