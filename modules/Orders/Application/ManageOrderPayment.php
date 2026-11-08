<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationManager;
use Litecommerce\Modules\Coupons\Contracts\InvalidCouponReservationStateException;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentStatus;
use Litecommerce\Modules\Orders\Contracts\OrderStatus;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderOutboxEvent;
use Litecommerce\Modules\Orders\Infrastructure\Models\OrderStatusHistory;
use Litecommerce\Modules\Warehouse\Contracts\InvalidReservationStateException;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationState;

final class ManageOrderPayment implements OrderPaymentManager
{
    public function __construct(
        private readonly StockReservationManager $stock,
        private readonly CouponReservationManager $coupons,
        private readonly DigitalAccessGrantor $digitalAccess,
        private readonly OrderEmailComposer $emails,
    ) {}

    public function confirm(string $orderId, \DateTimeImmutable $paidAt, string $attemptId): string
    {
        return DB::transaction(function () use ($orderId, $paidAt, $attemptId): string {
            $order = Order::query()->with(['lines', 'couponUsages'])->lockForUpdate()->findOrFail($orderId);

            if ($order->paid_attempt_id === $attemptId) {
                return 'already_confirmed';
            }
            if (in_array($order->payment_status, [OrderPaymentStatus::Paid->value, OrderPaymentStatus::Overpaid->value], true)) {
                $order->update(['payment_status' => OrderPaymentStatus::Overpaid->value, 'version' => $order->version + 1]);
                $this->outbox($order, 'OrderOverpaymentDetected', ['attempt_id' => $attemptId]);

                return 'overpayment';
            }

            if ($order->status === OrderStatus::Cancelled->value || $order->status === OrderStatus::PaymentReview->value) {
                return $this->late($order, $paidAt, $attemptId);
            }

            // Coupons before stock — see the fixed lock order note on
            // Orders\Application\PlaceOrder.
            try {
                foreach ($order->couponUsages as $usage) {
                    if ($usage->coupon_reservation_id !== null) {
                        $this->coupons->confirm((int) $usage->coupon_reservation_id);
                    }
                }

                foreach ($order->lines as $line) {
                    if ($line->stock_reservation_id !== null) {
                        $this->stock->confirm((int) $line->stock_reservation_id);
                    }
                }
            } catch (InvalidReservationStateException $exception) {
                if ($exception->currentState === StockReservationState::Expired || $exception->currentState === StockReservationState::Released) {
                    return $this->late($order, $paidAt, $attemptId);
                }
                throw $exception;
            } catch (InvalidCouponReservationStateException) {
                return $this->late($order, $paidAt, $attemptId);
            }

            $oldStatus = $order->status;
            $order->update([
                'payment_status' => OrderPaymentStatus::Paid->value, 'paid_at' => $paidAt,
                'paid_attempt_id' => $attemptId, 'status' => OrderStatus::Processing->value,
                'payment_deadline_at' => null, 'version' => $order->version + 1,
            ]);
            $this->history($order, $oldStatus, OrderStatus::Processing->value, 'Potwierdzono pełną wpłatę.');
            $this->outbox($order, 'PaymentConfirmed', ['attempt_id' => $attemptId, 'paid_at' => $paidAt->format(DATE_ATOM)]);

            // "Po potwierdzeniu zapłaty ... dostęp niezależny od wysyłki
            // części fizycznej" — granted only for this 'confirmed' branch,
            // never for late()'s Expired/Released outcome below, which
            // 05-orders.md requires a staff decision for instead.
            $digitalGrants = [];
            if ($order->mode !== 'test') {
                foreach ($order->lines as $line) {
                    if ($line->product_type === ProductType::Digital->value) {
                        array_push($digitalGrants, ...$this->digitalAccess->grantForOrderLine(
                            (int) $line->variant_id, 'order_line', (string) $line->getKey(), $order->id,
                            $order->customer_account_id, $order->email, $paidAt,
                        ));
                    }
                }
            }

            $this->emails->paymentConfirmed($order, $attemptId);
            $this->emails->digitalAccessGranted($order, $digitalGrants, $attemptId);

            return 'confirmed';
        });
    }

    public function cancelUnpaid(string $orderId, string $reason, ?string $actorId = null): void
    {
        DB::transaction(function () use ($orderId, $reason, $actorId): void {
            $order = Order::query()->with(['lines', 'couponUsages'])->lockForUpdate()->findOrFail($orderId);
            if ($order->payment_status !== OrderPaymentStatus::Unpaid->value || $order->status === OrderStatus::Cancelled->value) {
                return;
            }
            $this->releaseCoupons($order);
            foreach ($order->lines as $line) {
                if ($line->stock_reservation_id !== null) {
                    $this->stock->release((int) $line->stock_reservation_id);
                }
            }
            $old = $order->status;
            $order->update(['status' => OrderStatus::Cancelled->value, 'payment_deadline_at' => null, 'version' => $order->version + 1]);
            $this->history($order, $old, OrderStatus::Cancelled->value, $reason, $actorId);
            $this->outbox($order, 'OrderCancelled', ['reason' => $reason]);
        });
    }

    public function markForPaymentReview(string $orderId, string $reason): void
    {
        DB::transaction(function () use ($orderId, $reason): void {
            $order = Order::query()->with(['lines', 'couponUsages'])->lockForUpdate()->findOrFail($orderId);
            if ($order->payment_status !== OrderPaymentStatus::Unpaid->value) {
                return;
            }
            $this->releaseCoupons($order);
            foreach ($order->lines as $line) {
                if ($line->stock_reservation_id !== null) {
                    $this->stock->release((int) $line->stock_reservation_id);
                }
            }
            $old = $order->status;
            $order->update(['status' => OrderStatus::PaymentReview->value, 'payment_deadline_at' => null, 'version' => $order->version + 1]);
            $this->history($order, $old, OrderStatus::PaymentReview->value, $reason);
            $this->outbox($order, 'PaymentReviewRequired', ['reason' => $reason]);
        });
    }

    public function extendDeadlineOnce(string $orderId, \DateTimeImmutable $until): bool
    {
        return DB::transaction(function () use ($orderId, $until): bool {
            $order = Order::query()->lockForUpdate()->findOrFail($orderId);
            if ($order->deadline_extended || $order->payment_status !== OrderPaymentStatus::Unpaid->value) {
                return false;
            }
            $order->update(['deadline_extended' => true, 'payment_deadline_at' => $until, 'version' => $order->version + 1]);
            $this->outbox($order, 'PaymentDeadlineExtended', ['until' => $until->format(DATE_ATOM)]);

            return true;
        });
    }

    /**
     * release() is symmetric regardless of the reservation's prior state —
     * a still-Pending online reservation is simply unheld, while an
     * already-Confirmed one (COD/pay-on-pickup/free, consumed atomically
     * at placement) gets a compensating ledger credit. See
     * Coupons\Contracts\CouponReservationManager for the rationale.
     */
    private function releaseCoupons(Order $order): void
    {
        foreach ($order->couponUsages as $usage) {
            if ($usage->coupon_reservation_id !== null) {
                $this->coupons->release((int) $usage->coupon_reservation_id);
            }
        }
    }

    private function late(Order $order, \DateTimeImmutable $paidAt, string $attemptId): string
    {
        $order->update([
            'payment_status' => OrderPaymentStatus::LatePayment->value, 'paid_at' => $paidAt,
            'paid_attempt_id' => $attemptId, 'version' => $order->version + 1,
        ]);
        $this->outbox($order, 'LatePaymentReceived', ['attempt_id' => $attemptId]);

        return 'late_payment';
    }

    private function history(Order $order, ?string $old, string $new, string $reason, ?string $actorId = null): void
    {
        OrderStatusHistory::query()->create([
            'order_id' => $order->id, 'old_status' => $old, 'new_status' => $new,
            'actor_type' => $actorId === null ? 'system' : 'staff', 'actor_id' => $actorId,
            'reason' => $reason, 'occurred_at' => now(),
        ]);
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
}
