<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\InvalidFulfillmentTransitionException;
use Litecommerce\Modules\Orders\Contracts\OrderStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDelivery;

/** An order and its pickup delivery, both locked for the current transaction. */
final readonly class LockedPickupOrder
{
    public function __construct(public Order $order, public OrderDelivery $delivery) {}

    public static function find(string $orderId): ?self
    {
        $order = Order::query()->with(['lines', 'couponUsages'])->lockForUpdate()->findOrFail($orderId);
        $delivery = OrderDelivery::query()->where('order_id', $orderId)->lockForUpdate()->first();

        return $delivery === null || $delivery->kind !== 'pickup' ? null : new self($order, $delivery);
    }

    public static function require(string $orderId): self
    {
        return self::find($orderId) ?? throw new InvalidFulfillmentTransitionException(InvalidFulfillmentTransitionException::NOT_PICKUP, 'Zamówienie nie ma dostawy w formie odbioru osobistego.');
    }

    public function assertFulfillable(): void
    {
        if ($this->order->status === OrderStatus::Cancelled->value || $this->order->status === OrderStatus::PaymentReview->value) {
            throw new InvalidFulfillmentTransitionException(InvalidFulfillmentTransitionException::ORDER_NOT_FULFILLABLE, 'Zamówienie anulowane lub oczekujące na wyjaśnienie płatności nie może być realizowane.');
        }
    }

    public function isPayOnPickup(): bool
    {
        return $this->order->payment_kind === 'pay_on_pickup';
    }
}
