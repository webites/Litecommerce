<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Console;

use App\Support\Console\RunsBatches;
use Illuminate\Console\Command;
use Litecommerce\Modules\Consents\Application\PruneWithdrawnConsents;

final class PruneWithdrawnConsentsCommand extends Command
{
    use RunsBatches;

    protected $signature = 'consents:prune-withdrawn {--years= : Years to keep the proof of a withdrawn consent} {--batch= : Consents per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Remove the proof of marketing consents withdrawn longer ago than the retention period';

    public function handle(PruneWithdrawnConsents $pruner): int
    {
        $years = max(1, (int) ($this->option('years') ?: config('consents.withdrawn_evidence_retention_years', 6)));
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('consents.prune_batch', 200))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('consents.prune_max_batches', 10)));

        $removed = $this->runBatches(fn (int $size): int => $pruner->pruneBatch(now()->toDateTimeImmutable(), $years, $size), $batchSize, $maxBatches);

        $this->components->info("Removed {$removed} withdrawn consent(s).");

        return self::SUCCESS;
    }
}
