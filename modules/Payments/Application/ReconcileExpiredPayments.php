<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Illuminate\Support\Facades\Log;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentExpiryReconciler;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class ReconcileExpiredPayments implements PaymentExpiryReconciler
{
    public function __construct(
        private readonly OrderPaymentCatalog $orders,
        private readonly OrderPaymentManager $orderPayments,
        private readonly PaymentMethodRegistry $methods,
        private readonly ApplyPaymentResult $apply,
    ) {}

    public function reconcileDue(int $limit = 100): int
    {
        $processed = 0;
        foreach ($this->orders->dueOrderIds($limit) as $orderId) {
            try {
                $processed += $this->reconcileOrder($orderId);
            } catch (\Throwable $exception) {
                Log::warning('Payment expiry reconciliation failed for an order.', [
                    'order_id' => $orderId,
                    'error_code' => $exception::class,
                ]);
            }
        }

        return $processed;
    }

    private function reconcileOrder(string $orderId): int
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return 0;
        }

        $attempts = PaymentAttempt::query()->where('order_id', $orderId)
            ->whereIn('status', [PaymentAttemptStatus::Created->value, PaymentAttemptStatus::Pending->value])
            ->get();
        $uncertain = false;
        foreach ($attempts as $attempt) {
            if ($attempt->provider_transaction_id === null) {
                $uncertain = true;

                continue;
            }
            $method = $this->methods->get($attempt->method_id);
            if ($method->descriptor()->kind === 'bank_transfer') {
                continue;
            }
            try {
                $result = $method->query($attempt->provider_transaction_id);
                $this->apply->execute($attempt->id, $result);
                $uncertain = $uncertain || in_array($result->status, [PaymentAttemptStatus::Created, PaymentAttemptStatus::Pending], true);
            } catch (\Throwable) {
                $uncertain = true;
            }
        }

        $fresh = $this->orders->find($orderId);
        if ($fresh !== null && $fresh->paymentStatus === 'unpaid') {
            if ($uncertain && $this->orderPayments->extendDeadlineOnce(
                $orderId,
                new \DateTimeImmutable('+'.(int) config('orders.uncertain_payment_extension_minutes', 15).' minutes'),
            )) {
                return 1;
            }
            if ($uncertain) {
                $this->orderPayments->markForPaymentReview($orderId, 'Operator nie potwierdził stanu płatności po jednorazowym przedłużeniu.');
            } else {
                $this->orderPayments->cancelUnpaid($orderId, 'Termin płatności minął bez potwierdzonej wpłaty.');
            }
        }

        return 1;
    }
}
