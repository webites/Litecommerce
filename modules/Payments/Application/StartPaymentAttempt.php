<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptData;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStarter;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentContext;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;

final class StartPaymentAttempt implements PaymentAttemptStarter
{
    public function __construct(
        private readonly OrderPaymentCatalog $orders,
        private readonly PaymentMethodRegistry $methods,
        private readonly PaymentAttemptProjector $projector,
    ) {}

    public function start(string $orderId, string $customerEmail, ?string $returnUrl = null): ?PaymentAttemptData
    {
        $order = $this->orders->find($orderId) ?? throw new \InvalidArgumentException("Unknown order [{$orderId}].");
        if (bccomp($order->amount, '0', 6) === 0) {
            return null;
        }

        $method = $this->methods->get($order->paymentMethodId);
        $descriptor = $method->descriptor();
        // One deterministic initial attempt per order makes a replay of the
        // checkout response idempotent. Explicit retries get a new UUID.
        $attemptId = $orderId;

        return $this->startWithId($orderId, $attemptId, $customerEmail, $returnUrl);
    }

    public function retry(string $orderId, string $customerEmail, ?string $returnUrl = null): PaymentAttemptData
    {
        $order = $this->orders->find($orderId) ?? throw new \InvalidArgumentException("Unknown order [{$orderId}].");
        if ($order->paymentStatus !== 'unpaid' || $order->status === 'cancelled' || bccomp($order->amount, '0', 6) === 0) {
            throw new \DomainException('Nie można ponowić płatności dla tego zamówienia.');
        }

        return $this->startWithId($orderId, (string) Str::uuid(), $customerEmail, $returnUrl);
    }

    private function startWithId(string $orderId, string $attemptId, string $customerEmail, ?string $returnUrl): PaymentAttemptData
    {
        $order = $this->orders->find($orderId) ?? throw new \InvalidArgumentException("Unknown order [{$orderId}].");
        $method = $this->methods->get($order->paymentMethodId);
        $descriptor = $method->descriptor();
        $existing = PaymentAttempt::query()->find($attemptId);
        if ($existing !== null) {
            return $this->projector->project($existing);
        }

        $attempt = PaymentAttempt::query()->create([
            'id' => $attemptId, 'order_id' => $orderId, 'provider_id' => $descriptor->providerId,
            'method_id' => $descriptor->id, 'method_name' => $descriptor->name, 'status' => PaymentAttemptStatus::Created->value,
            'amount' => $order->amount, 'currency_code' => $order->currencyCode, 'mode' => $order->mode,
        ]);

        if ($order->mode === 'test' && $descriptor->providerId !== 'core') {
            return $this->projector->project($attempt);
        }

        try {
            $result = $method->start(new PaymentContext(
                $orderId, $order->number, $attemptId, $order->amount, $order->currencyCode, $order->mode, $customerEmail, $order->locale, $returnUrl,
            ));
            $attempt->update([
                'provider_transaction_id' => $result->providerTransactionId,
                'status' => $result->status->value,
                'metadata' => [...$result->metadata, 'redirect_url' => $result->redirectUrl],
            ]);
        } catch (\Throwable $exception) {
            $attempt->update([
                'status' => PaymentAttemptStatus::Failed->value,
                'failure_code' => 'initiation_failed', 'failure_message' => $exception->getMessage(),
            ]);
        }

        return $this->projector->project($attempt->fresh());
    }
}
