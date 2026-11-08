<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Application;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Payments\Contracts\InvalidPaymentNotificationException;
use Litecommerce\Modules\Payments\Infrastructure\Models\DeferredPaymentWebhook;

/** Feeds notifications stored during maintenance through the normal verification path once maintenance is over. */
final class ReplayDeferredPaymentWebhooks
{
    public function __construct(private readonly MaintenanceMode $maintenance, private readonly ProcessPaymentNotification $processor) {}

    public function replayBatch(int $batchSize): int
    {
        if ($this->maintenance->isActive()) {
            return 0;
        }

        $this->releaseStaleClaims();
        $handled = 0;

        foreach ($this->nextPendingIds($batchSize) as $id) {
            if (! $this->claim($id)) {
                continue;
            }

            $this->replay(DeferredPaymentWebhook::query()->findOrFail($id));
            $handled++;
        }

        return $handled;
    }

    public function pruneFinished(\DateTimeImmutable $now, int $retentionDays): int
    {
        return DeferredPaymentWebhook::query()
            ->whereIn('status', [DeferredPaymentWebhook::PROCESSED, DeferredPaymentWebhook::REJECTED, DeferredPaymentWebhook::FAILED])
            ->where('finished_at', '<', $now->modify("-{$retentionDays} days"))
            ->delete();
    }

    private function replay(DeferredPaymentWebhook $webhook): void
    {
        try {
            $this->processor->execute($webhook->method_id, $webhook->body, $webhook->headers);
            $this->finish($webhook, DeferredPaymentWebhook::PROCESSED, null);
        } catch (InvalidPaymentNotificationException|\InvalidArgumentException $exception) {
            $this->finish($webhook, DeferredPaymentWebhook::REJECTED, $exception::class);
        } catch (ModelNotFoundException $exception) {
            $this->finish($webhook, DeferredPaymentWebhook::FAILED, $exception::class);
        } catch (\Throwable $exception) {
            report($exception);
            $attempts = $webhook->attempts + 1;
            $exhausted = $attempts >= (int) config('payments.deferred_webhook_max_attempts', 5);

            $webhook->forceFill([
                'attempts' => $attempts,
                'last_error_code' => $exception::class,
                'status' => $exhausted ? DeferredPaymentWebhook::FAILED : DeferredPaymentWebhook::PENDING,
                'finished_at' => $exhausted ? now() : null,
                'claimed_at' => null,
            ])->save();
        }
    }

    private function finish(DeferredPaymentWebhook $webhook, string $status, ?string $errorCode): void
    {
        $webhook->forceFill(['status' => $status, 'last_error_code' => $errorCode, 'finished_at' => now(), 'claimed_at' => null])->save();
    }

    /** @return list<string> */
    private function nextPendingIds(int $batchSize): array
    {
        return DeferredPaymentWebhook::query()
            ->where('status', DeferredPaymentWebhook::PENDING)
            ->orderBy('received_at')->orderBy('id')->limit(max(1, $batchSize))->pluck('id')->all();
    }

    private function claim(string $id): bool
    {
        return DeferredPaymentWebhook::query()
            ->whereKey($id)->where('status', DeferredPaymentWebhook::PENDING)
            ->update(['status' => DeferredPaymentWebhook::PROCESSING, 'claimed_at' => now()]) === 1;
    }

    private function releaseStaleClaims(): void
    {
        DeferredPaymentWebhook::query()
            ->where('status', DeferredPaymentWebhook::PROCESSING)
            ->where('claimed_at', '<', now()->subMinutes((int) config('payments.deferred_webhook_claim_timeout_minutes', 15)))
            ->update(['status' => DeferredPaymentWebhook::PENDING, 'claimed_at' => null]);
    }
}
