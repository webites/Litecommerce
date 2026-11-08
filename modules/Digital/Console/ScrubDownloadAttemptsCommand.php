<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Console;

use App\Support\Console\RunsBatches;
use Illuminate\Console\Command;
use Litecommerce\Modules\Digital\Application\ScrubDownloadAttemptIdentifiers;

final class ScrubDownloadAttemptsCommand extends Command
{
    use RunsBatches;

    protected $signature = 'digital:scrub-download-attempts {--days= : Age in days after which identifiers are cleared} {--batch= : Attempts per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Clear the IP address and browser identifier from old download attempts';

    public function handle(ScrubDownloadAttemptIdentifiers $scrubber): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('digital.attempt_identifier_retention_days', 90)));
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('digital.scrub_batch', 500))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('digital.scrub_max_batches', 10)));
        $olderThan = now()->subDays($days)->toDateTimeImmutable();

        $cleared = $this->runBatches(fn (int $size): int => $scrubber->scrubBatch($olderThan, $size), $batchSize, $maxBatches);

        $this->components->info("Cleared identifiers from {$cleared} download attempt(s).");

        return self::SUCCESS;
    }
}
