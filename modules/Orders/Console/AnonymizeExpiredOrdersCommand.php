<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Console;

use App\Support\Console\RunsBatches;
use App\Support\Privacy\RetentionCutoff;
use Illuminate\Console\Command;
use Litecommerce\Modules\Orders\Application\AnonymizeExpiredOrders;

final class AnonymizeExpiredOrdersCommand extends Command
{
    use RunsBatches;

    protected $signature = 'orders:anonymize-expired {--years= : Retention years after the end of the order year} {--batch= : Orders per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Remove personal data from orders whose accounting retention period ended';

    public function handle(AnonymizeExpiredOrders $anonymizer): int
    {
        $years = max(1, (int) ($this->option('years') ?: config('privacy.order_data_retention_years', 5)));
        $batchSize = max(1, min(500, (int) ($this->option('batch') ?: config('privacy.anonymize_batch', 100))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('privacy.anonymize_max_batches', 10)));
        $cutoff = RetentionCutoff::orderData(now(), $years);

        $done = $this->runBatches(fn (int $size): int => $anonymizer->anonymizeBatch($cutoff, $size), $batchSize, $maxBatches);

        $this->components->info("Anonymized {$done} order(s).");

        return self::SUCCESS;
    }
}
