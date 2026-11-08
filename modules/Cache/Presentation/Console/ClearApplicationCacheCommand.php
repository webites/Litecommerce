<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Presentation\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;

final class ClearApplicationCacheCommand extends Command
{
    protected $signature = 'litecommerce:cache-clear';

    protected $description = 'Invalidate all Litecommerce object cache groups without flushing the shared store';

    public function handle(CacheMaintenance $cache): int
    {
        $cache->clearApplicationCache();
        $this->info('Litecommerce application cache was cleared.');

        return self::SUCCESS;
    }
}
