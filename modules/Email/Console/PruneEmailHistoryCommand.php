<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Email\Application\PruneEmailHistory;

final class PruneEmailHistoryCommand extends Command
{
    protected $signature = 'email:prune-history {--days= : Retention period in days} {--batch= : Messages per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Remove terminal email history older than the configured retention period';

    public function handle(PruneEmailHistory $pruner): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('email.history_retention_days', 365)));
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('email.history_prune_batch', 100))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('email.history_prune_max_batches', 10)));
        $removed = 0;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $count = $pruner->pruneBatch($days, $batchSize);
            $removed += $count;

            if ($count < $batchSize) {
                break;
            }
        }

        $this->components->info("Removed {$removed} expired email history record(s).");

        return self::SUCCESS;
    }
}
