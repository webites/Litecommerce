<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptData;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Contracts\PaymentReferenceAlreadyUsedException;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class ConfirmManualPayment implements PaymentConfirmation
{
    public function __construct(
        private readonly OrderPaymentCatalog $orders,
        private readonly PaymentMethodRegistry $methods,
        private readonly ApplyPaymentResult $apply,
    ) {}

    public function confirmManual(string $orderId, string $reference, \DateTimeImmutable $paidAt, string $actorId): PaymentAttemptData
    {
        $order = $this->orders->find($orderId) ?? throw new \InvalidArgumentException("Unknown order [{$orderId}].");
        $method = $this->methods->get($order->paymentMethodId);
        // Cash on delivery and pay on pickup are confirmed manually through this same mechanism.
        if (! in_array($method->descriptor()->kind, ['bank_transfer', 'cash_on_delivery', 'pay_on_pickup'], true)) {
            throw new \DomainException('Ręczne potwierdzenie w MVP jest dostępne tylko dla przelewu, pobrania i płatności na miejscu.');
        }

        $normalized = trim($reference);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Payment reference is required.');
        }
        $transactionId = 'manual:'.$normalized;
        $attempt = PaymentAttempt::query()
            ->where('provider_id', 'core')->where('method_id', $method->descriptor()->id)
            ->where('provider_transaction_id', $transactionId)->first();

        if ($attempt === null) {
            $attempt = PaymentAttempt::query()->where('order_id', $orderId)
                ->where('provider_id', 'core')->where('method_id', $method->descriptor()->id)
                ->whereIn('status', [PaymentAttemptStatus::Created->value, PaymentAttemptStatus::Pending->value])
                ->oldest()->first();
            if ($attempt !== null) {
                $attempt->update(['provider_transaction_id' => $transactionId, 'metadata' => ['confirmed_by_staff_id' => $actorId]]);
            } else {
                $attempt = PaymentAttempt::query()->create([
                    'id' => (string) Str::uuid(), 'order_id' => $orderId, 'provider_id' => 'core',
                    'method_id' => $method->descriptor()->id, 'method_name' => $method->descriptor()->name, 'provider_transaction_id' => $transactionId,
                    'status' => PaymentAttemptStatus::Pending->value, 'amount' => $order->amount,
                    'currency_code' => $order->currencyCode, 'mode' => $order->mode,
                    'metadata' => ['confirmed_by_staff_id' => $actorId],
                ]);
            }
        } elseif ($attempt->order_id !== $orderId) {
            throw new PaymentReferenceAlreadyUsedException('Ta referencja wpłaty została już użyta przy innym zamówieniu.');
        }

        return $this->apply->execute($attempt->id, new PaymentStatusResult(PaymentAttemptStatus::Succeeded, $paidAt));
    }
}
