<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Console;

use App\Support\Console\RunsBatches;
use Illuminate\Console\Command;
use Litecommerce\Modules\Orders\Application\PruneGuestOrderTokens;

final class PruneGuestOrderTokensCommand extends Command
{
    use RunsBatches;

    protected $signature = 'orders:prune-guest-tokens {--batch= : Tokens per batch} {--max-batches= : Maximum batches per run}';

    protected $description = 'Remove expired guest order access tokens';

    public function handle(PruneGuestOrderTokens $pruner): int
    {
        $batchSize = max(1, min(1000, (int) ($this->option('batch') ?: config('orders.guest_token_prune_batch', 500))));
        $maxBatches = max(1, (int) ($this->option('max-batches') ?: config('orders.guest_token_prune_max_batches', 10)));

        $removed = $this->runBatches(fn (int $size): int => $pruner->pruneBatch(now()->toDateTimeImmutable(), $size), $batchSize, $maxBatches);

        $this->components->info("Removed {$removed} expired guest order token(s).");

        return self::SUCCESS;
    }
}
