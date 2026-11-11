<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationManager;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Orders\Contracts\Events\OrderPlaced;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Contracts\OrderPlacementLineData;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Orders\Contracts\OrderStatus;
use Litecommerce\Modules\Orders\Contracts\PlacedOrderData;
use Litecommerce\Modules\Orders\Contracts\PlaceOrderData;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderCouponUsage;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderDelivery;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderLine;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderOutboxEvent;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderPartySnapshot;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderStatusHistory;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;

final class PlaceOrder implements OrderPlacer
{
    public function __construct(
        private readonly StockReservationManager $stock,
        private readonly CouponReservationManager $coupons,
        private readonly DigitalAccessGrantor $digitalAccess,
        private readonly OrderProjector $projector,
        private readonly OrderEmailComposer $emails,
        private readonly ProductTypeRegistry $productTypes,
        private readonly OrderNumberAllocator $numbers,
    ) {}

    public function place(PlaceOrderData $data): PlacedOrderData
    {
        return DB::transaction(function () use ($data): PlacedOrderData {
            $existing = Order::query()->where('source_type', $data->sourceType)->where('source_id', $data->sourceId)->lockForUpdate()->first();
            if ($existing !== null) {
                return $this->projector->placed($existing);
            }

            $now = now();
            $deadline = match ($data->paymentKind) {
                'online' => $now->copy()->addMinutes((int) config('orders.online_reservation_minutes', 60)),
                'bank_transfer' => $now->copy()->addDays((int) config('orders.bank_transfer_deadline_days', 5)),
                default => null,
            };
            // Online reservations outlive the payment deadline by the one allowed uncertainty extension.
            $reservationDeadline = $data->paymentKind === 'online' && $deadline !== null
                ? $deadline->copy()->addMinutes((int) config('orders.uncertain_payment_extension_minutes', 15))
                : $deadline;
            $immediateReservationConfirmation = in_array($data->paymentKind, ['cash_on_delivery', 'pay_on_pickup', 'free'], true);
            $alreadyPaid = $data->paymentKind === 'free';
            $id = (string) Str::uuid();

            $order = Order::query()->create([
                'id' => $id, 'source_type' => $data->sourceType, 'source_id' => $data->sourceId,
                'customer_account_id' => $data->customerAccountId, 'email' => $data->email,
                'mode' => $data->testMode ? 'test' : 'live', 'locale' => $data->locale,
                'currency_code' => $data->currencyCode,
                'status' => $alreadyPaid ? OrderStatus::Processing->value : OrderStatus::PendingPayment->value,
                'payment_status' => $alreadyPaid ? OrderPaymentStatus::Paid->value : OrderPaymentStatus::Unpaid->value,
                'payment_method_id' => $data->paymentMethodId, 'payment_kind' => $data->paymentKind,
                'total_net_amount' => $data->totalNetAmount, 'total_vat_amount' => $data->totalVatAmount,
                'total_gross_amount' => $data->totalGrossAmount, 'payment_deadline_at' => $deadline,
                'paid_at' => $alreadyPaid ? $now : null,
                'version' => 1,
            ]);

            OrderPartySnapshot::query()->create([
                'order_id' => $id, 'buyer_type' => $data->buyerType, 'email' => $data->email,
                'company_name' => $data->companyName, 'tax_identifier' => $data->taxIdentifier,
                'registration_number' => $data->registrationNumber, 'phone' => $data->phone,
                'billing_address' => $data->billingAddress, 'shipping_address' => $data->shippingAddress,
            ]);

            if ($data->deliveryMethodId !== null) {
                OrderDelivery::query()->create([
                    'order_id' => $id, 'delivery_method_id' => $data->deliveryMethodId,
                    'kind' => $data->deliveryKind, 'name' => $data->deliveryName,
                    'net_amount' => $data->deliveryNetAmount, 'vat_amount' => $data->deliveryVatAmount,
                    'gross_amount' => $data->deliveryGrossAmount,
                    'pickup_point_id' => $data->deliveryPickupPointId,
                    'pickup_point_snapshot' => $data->deliveryPickupPointSnapshot,
                ]);
            }

            // Coupons are reserved before stock to keep one lock order across operations.
            foreach ($data->couponUsages as $usage) {
                $usageRow = OrderCouponUsage::query()->create([
                    'order_id' => $id, 'coupon_code' => $usage['code'],
                    'product_gross_amount' => $usage['product_gross_amount'], 'delivery_gross_amount' => $usage['delivery_gross_amount'],
                ]);

                if (! $data->testMode && bccomp($usage['base_amount'], '0', 6) > 0) {
                    $reservation = $this->coupons->reserve(
                        $usage['code'], $usage['base_amount'], 'order', $id,
                        $reservationDeadline === null ? null : \DateTimeImmutable::createFromInterface($reservationDeadline),
                    );
                    $usageRow->update(['coupon_reservation_id' => $reservation->id]);
                    if ($immediateReservationConfirmation) {
                        $this->coupons->confirm($reservation->id);
                    }
                }
            }

            $lines = [];

            foreach ($data->lines as $lineData) {
                $lines[] = [
                    'data' => $lineData,
                    'model' => OrderLine::query()->create([
                        'order_id' => $id, 'variant_id' => $lineData->variantId,
                        'product_type' => $lineData->productType, 'sku' => $lineData->sku,
                        'display_name' => $lineData->displayName, 'quantity' => $lineData->quantity,
                        'unit_amount' => $lineData->unitAmount, 'line_net_amount' => $lineData->lineNetAmount,
                        'line_vat_amount' => $lineData->lineVatAmount, 'line_gross_amount' => $lineData->lineGrossAmount,
                        'allocations_snapshot' => $this->allocationsSnapshot($lineData),
                    ]),
                ];
            }

            // Stock is reserved in ascending variant order so concurrent orders cannot deadlock.
            $physicalLines = array_values(array_filter(
                $lines,
                fn (array $entry): bool => $this->productTypes->get($entry['data']->productType)->tracksStock,
            ));
            usort($physicalLines, static fn (array $a, array $b): int => $a['data']->variantId <=> $b['data']->variantId);

            if (! $data->testMode) {
                foreach ($physicalLines as $entry) {
                    $reservation = $this->stock->reserve(
                        $entry['data']->variantId, $entry['data']->quantity, 'order_line', (string) $entry['model']->getKey(),
                        $reservationDeadline === null ? null : \DateTimeImmutable::createFromInterface($reservationDeadline),
                    );
                    $entry['model']->update(['stock_reservation_id' => $reservation->id]);
                    if ($immediateReservationConfirmation) {
                        $this->stock->confirm($reservation->id);
                    }
                }
            }

            // A free order is paid on placement, so this is its only chance to grant digital access.
            $digitalGrants = [];
            if (! $data->testMode && $alreadyPaid) {
                foreach ($lines as $entry) {
                    if ($this->productTypes->get($entry['data']->productType)->grantsDigitalAccess) {
                        array_push($digitalGrants, ...$this->digitalAccess->grantForOrderLine(
                            $entry['data']->variantId, 'order_line', (string) $entry['model']->getKey(), $id,
                            $data->customerAccountId, $data->email, \DateTimeImmutable::createFromInterface($now),
                        ));
                    }
                }
            }

            $number = $this->numbers->next();
            $order->update(['number' => $number['number'], 'public_number' => $number['label']]);

            OrderStatusHistory::query()->create([
                'order_id' => $id, 'old_status' => null, 'new_status' => $order->status,
                'actor_type' => 'system', 'occurred_at' => $now,
                'reason' => $data->customerNotes,
            ]);
            $this->outbox($order, 'OrderPlaced', ['payment_method_id' => $data->paymentMethodId]);

            // Plugins are notified only after the transaction commits, never on a rollback.
            DB::afterCommit(fn () => CommittedEvent::dispatch(new OrderPlaced($id)));

            $this->emails->orderPlaced($order);
            $this->emails->digitalAccessGranted($order, $digitalGrants, 'free-order');

            return $this->projector->placed($order);
        });
    }

    public function findBySource(string $sourceType, string $sourceId): ?PlacedOrderData
    {
        $order = Order::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();

        return $order === null ? null : $this->projector->placed($order);
    }

    /** @param array<string, mixed> $payload */
    private function outbox(Order $order, string $type, array $payload): void
    {
        OrderOutboxEvent::query()->create([
            'id' => (string) Str::uuid(), 'order_id' => $order->id, 'event_type' => $type,
            'aggregate_version' => $order->version, 'payload' => $payload,
            'occurred_at' => now(), 'available_at' => now(),
        ]);
    }

    /** @return array<string, list<array<string, string>>>|null */
    private function allocationsSnapshot(OrderPlacementLineData $line): ?array
    {
        $snapshot = array_filter([
            'coupons' => $line->couponAllocations,
            'adjustments' => $line->adjustmentAllocations,
        ]);

        return $snapshot === [] ? null : $snapshot;
    }
}
