<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Console;

use App\Support\Console\RunsBatches;
use Illuminate\Console\Command;
use Litecommerce\Modules\Payments\Application\ReplayDeferredPaymentWebhooks;

final class ReplayDeferredPaymentWebhooksCommand extends Command
{
    use RunsBatches;

    protected $signature = 'payments:replay-deferred-webhooks {--batch= : Notifications per batch} {--max-batches=10 : Maximum batches per run}';

    protected $description = 'Process payment notifications stored during maintenance and prune finished ones';

    public function handle(ReplayDeferredPaymentWebhooks $replay): int
    {
        $batchSize = max(1, min(500, (int) ($this->option('batch') ?: config('payments.deferred_webhook_batch', 50))));
        $maxBatches = max(1, (int) $this->option('max-batches'));

        $handled = $this->runBatches(fn (int $size): int => $replay->replayBatch($size), $batchSize, $maxBatches);
        $pruned = $replay->pruneFinished(now()->toDateTimeImmutable(), (int) config('payments.deferred_webhook_retention_days', 30));

        $this->components->info("Replayed {$handled} deferred notification(s), pruned {$pruned}.");

        return self::SUCCESS;
    }
}
