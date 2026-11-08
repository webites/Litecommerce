<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Console;

use App\Support\Console\RunsBatches;
use Illuminate\Console\Command;
use Litecommerce\Modules\Audit\Application\PruneAuditEntries;

final class PruneAuditEntriesCommand extends Command
{
    use RunsBatches;

    protected $signature = 'audit:prune {--months= : Retention period in months} {--batch= : Entries per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Remove audit entries older than the retention period, except kinds kept indefinitely, and clear old visitor identifiers';

    public function handle(PruneAuditEntries $pruner): int
    {
        $months = max(1, (int) ($this->option('months') ?: config('audit.retention_months', 24)));
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('audit.prune_batch', 200))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('audit.prune_max_batches', 10)));
        $olderThan = now()->subMonths($months)->toDateTimeImmutable();
        $identifierCutoff = now()->subDays((int) config('audit.identifier_retention_days', 90))->toDateTimeImmutable();

        $removed = $this->runBatches(fn (int $size): int => $pruner->pruneBatch($olderThan, $size), $batchSize, $maxBatches);
        $scrubbed = $this->runBatches(fn (int $size): int => $pruner->scrubIdentifiers($identifierCutoff, $size), $batchSize, $maxBatches);

        $this->components->info("Removed {$removed} expired audit entr(ies); cleared visitor identifiers from {$scrubbed}.");

        return self::SUCCESS;
    }
}
