<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Search\Contracts\SearchIndex;

final class RebuildSearchIndexCommand extends Command
{
    protected $signature = 'search:rebuild';

    protected $description = 'Rebuild the storefront product search projection';

    public function handle(SearchIndex $index): int
    {
        $index->rebuild();
        $this->info('Search index rebuilt.');

        return self::SUCCESS;
    }
}
