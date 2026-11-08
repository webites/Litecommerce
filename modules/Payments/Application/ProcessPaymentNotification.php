<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentNotification;

final class ProcessPaymentNotification
{
    public function __construct(
        private readonly PaymentMethodRegistry $methods,
        private readonly ApplyPaymentResult $apply,
    ) {}

    /** @param array<string, string> $headers */
    public function execute(string $methodId, string $rawBody, array $headers): void
    {
        $method = $this->methods->get($methodId);
        $verified = $method->verifyNotification($rawBody, $headers);
        $attempt = PaymentAttempt::query()
            ->where('provider_id', $method->descriptor()->providerId)
            ->where('method_id', $methodId)
            ->where('provider_transaction_id', $verified->providerTransactionId)
            ->firstOrFail();
        $hash = hash('sha256', $rawBody);

        try {
            PaymentNotification::query()->create([
                'id' => (string) Str::uuid(), 'attempt_id' => $attempt->id,
                'provider_id' => $method->descriptor()->providerId,
                'external_notification_id' => $verified->externalNotificationId,
                'payload_hash' => $hash, 'mapped_status' => $verified->status->value,
                'sanitized_payload' => $verified->sanitizedPayload, 'received_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if (PaymentNotification::query()->where('provider_id', $method->descriptor()->providerId)->where('payload_hash', $hash)->exists()) {
                return;
            }
            throw $exception;
        }

        $this->apply->execute($attempt->id, new PaymentStatusResult($verified->status, $verified->paidAt));
    }
}
