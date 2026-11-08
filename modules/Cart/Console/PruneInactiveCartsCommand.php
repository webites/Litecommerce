<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Console;

use App\Support\Console\RunsBatches;
use Illuminate\Console\Command;
use Litecommerce\Modules\Cart\Application\PruneInactiveCarts;

final class PruneInactiveCartsCommand extends Command
{
    use RunsBatches;

    protected $signature = 'cart:prune-inactive {--guest-days= : Retention of a guest cart in days} {--customer-days= : Retention of a customer cart in days} {--batch= : Carts per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Remove carts and checkout drafts idle longer than the retention period';

    public function handle(PruneInactiveCarts $pruner): int
    {
        $guestDays = max(1, (int) ($this->option('guest-days') ?: config('cart.guest_retention_days', 30)));
        $customerDays = max(1, (int) ($this->option('customer-days') ?: config('cart.customer_retention_days', 90)));
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('cart.prune_batch', 200))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('cart.prune_max_batches', 10)));

        $removed = $this->runBatches(fn (int $size): int => $pruner->pruneBatch(now()->toDateTimeImmutable(), $guestDays, $customerDays, $size), $batchSize, $maxBatches);

        $this->components->info("Removed {$removed} inactive cart(s).");

        return self::SUCCESS;
    }
}
