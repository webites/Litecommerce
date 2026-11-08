<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Illuminate\Support\Str;
use Litecommerce\Modules\Payments\Infrastructure\Models\DeferredPaymentWebhook;

/**
 * Stores a provider notification verbatim while maintenance is on, so ingress never depends on the code
 * being updated. Verification and processing happen later in ReplayDeferredPaymentWebhooks.
 */
final class DeferPaymentWebhook
{
    public const ACCEPTED = 'accepted';

    public const TOO_LARGE = 'too_large';

    public const FULL = 'full';

    /** @param array<string, string> $headers */
    public function execute(string $methodId, string $rawBody, array $headers): string
    {
        if (strlen($rawBody) > (int) config('payments.deferred_webhook_max_body_bytes', 65536)) {
            return self::TOO_LARGE;
        }

        $pending = DeferredPaymentWebhook::query()->where('status', DeferredPaymentWebhook::PENDING)->count();
        if ($pending >= (int) config('payments.deferred_webhook_max_pending', 5000)) {
            return self::FULL;
        }

        unset($headers['cookie']);

        DeferredPaymentWebhook::query()->create([
            'id' => (string) Str::uuid(),
            'method_id' => $methodId,
            'body' => $rawBody,
            'headers' => $headers,
            'status' => DeferredPaymentWebhook::PENDING,
            'received_at' => now(),
        ]);

        return self::ACCEPTED;
    }
}
