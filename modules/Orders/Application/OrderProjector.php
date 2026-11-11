<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\DeliveryFulfillmentState;
use Litecommerce\Modules\Orders\Contracts\OrderDetailData;
use Litecommerce\Modules\Orders\Contracts\OrderLineDetailData;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentData;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Contracts\OrderReturnableData;
use Litecommerce\Modules\Orders\Contracts\PickupFulfillmentData;
use Litecommerce\Modules\Orders\Contracts\PlacedOrderData;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderLine;

final class OrderProjector
{
    public function placed(Order $order): PlacedOrderData
    {
        return new PlacedOrderData(
            $order->id, $order->public_number, $order->status, $order->payment_status, $order->payment_method_id,
            (string) $order->total_gross_amount, $order->currency_code,
            $order->payment_deadline_at === null ? null : \DateTimeImmutable::createFromInterface($order->payment_deadline_at),
        );
    }

    public function payment(Order $order): OrderPaymentData
    {
        return new OrderPaymentData(
            $order->id, $order->public_number, $order->status, $order->payment_status, $order->payment_method_id,
            (string) $order->total_gross_amount, $order->currency_code, $order->mode, $order->locale, $order->version,
            $order->payment_deadline_at === null ? null : \DateTimeImmutable::createFromInterface($order->payment_deadline_at),
        );
    }

    /** Assumes $order was loaded with the "lines" and "delivery" relations. */
    public function returnable(Order $order): OrderReturnableData
    {
        return new OrderReturnableData(
            $order->id, $order->public_number, $order->currency_code, $order->payment_status,
            $order->paid_at === null ? null : \DateTimeImmutable::createFromInterface($order->paid_at),
            $order->mode, $order->locale, $order->customer_account_id, $order->email,
            $order->delivery === null ? null : (string) $order->delivery->net_amount,
            $order->delivery === null ? null : (string) $order->delivery->vat_amount,
            $order->delivery === null ? null : (string) $order->delivery->gross_amount,
            $order->lines->map(fn (OrderLine $line): OrderLineDetailData => new OrderLineDetailData(
                (int) $line->getKey(), (int) $line->variant_id, $line->product_type, $line->sku, $line->display_name,
                (string) $line->quantity, (string) $line->line_net_amount, (string) $line->line_vat_amount, (string) $line->line_gross_amount,
                $this->couponAllocations($line->allocations_snapshot), $line->allocations_snapshot['adjustments'] ?? [],
            ))->values()->all(),
        );
    }

    /** Assumes $order was loaded with the "party", "delivery" and "lines" relations. */
    public function detail(Order $order): OrderDetailData
    {
        $party = $order->party;

        return new OrderDetailData(
            $order->id, $order->public_number, $order->status, $order->payment_status, $order->payment_method_id,
            $order->currency_code, $order->mode, $order->locale,
            $party->email, $party->buyer_type, $party->company_name,
            $party->billing_address, $party->shipping_address,
            (string) $order->total_net_amount, (string) $order->total_vat_amount, (string) $order->total_gross_amount,
            $order->delivery?->name,
            $order->delivery === null ? null : (string) $order->delivery->net_amount,
            $order->delivery === null ? null : (string) $order->delivery->vat_amount,
            $order->delivery === null ? null : (string) $order->delivery->gross_amount,
            $order->delivery?->pickup_point_snapshot,
            $order->lines->map(fn (OrderLine $line): OrderLineDetailData => new OrderLineDetailData(
                (int) $line->getKey(), (int) $line->variant_id, $line->product_type, $line->sku, $line->display_name,
                (string) $line->quantity, (string) $line->line_net_amount, (string) $line->line_vat_amount, (string) $line->line_gross_amount,
                $this->couponAllocations($line->allocations_snapshot), $line->allocations_snapshot['adjustments'] ?? [],
            ))->values()->all(),
            $order->paid_at === null ? null : \DateTimeImmutable::createFromInterface($order->paid_at),
            $order->created_at === null ? null : \DateTimeImmutable::createFromInterface($order->created_at),
            $this->pickup($order),
        );
    }

    private function pickup(Order $order): ?PickupFulfillmentData
    {
        $delivery = $order->delivery;

        if ($delivery === null || $delivery->kind !== 'pickup') {
            return null;
        }

        $immutable = static fn ($date): ?\DateTimeImmutable => $date === null ? null : \DateTimeImmutable::createFromInterface($date);

        return new PickupFulfillmentData(
            DeliveryFulfillmentState::from($delivery->fulfillment_state),
            $immutable($delivery->ready_for_pickup_at), $immutable($delivery->pickup_deadline_at), $immutable($delivery->handed_over_at),
            $order->payment_kind === 'pay_on_pickup' && $order->payment_status === OrderPaymentStatus::Unpaid->value,
        );
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array{code:string,amount:string,base_amount:string}>
     */
    private function couponAllocations(?array $snapshot): array
    {
        if ($snapshot === null || ! isset($snapshot['coupons']) || ! is_array($snapshot['coupons'])) {
            return [];
        }

        return $snapshot['coupons'];
    }
}
