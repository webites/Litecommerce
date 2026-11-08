<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Presentation\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;

final class CacheStatusCommand extends Command
{
    protected $signature = 'litecommerce:cache-status';

    protected $description = 'Check the configured Litecommerce object cache store';

    public function handle(CacheMaintenance $cache): int
    {
        $status = $cache->status();

        $this->table(['Store', 'Available', 'Locks', 'Tags'], [[
            $status->store,
            $status->available ? 'yes' : 'no',
            $status->supportsLocks ? 'yes' : 'no',
            $status->supportsTags ? 'yes' : 'no',
        ]]);

        return $status->available ? self::SUCCESS : self::FAILURE;
    }
}
