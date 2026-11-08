<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Pricing\Contracts\Events\PricingIndexChanged;
use Litecommerce\Modules\Search\Console\RebuildSearchIndexCommand;
use Litecommerce\Modules\Search\Contracts\ProductSearchEngine;
use Litecommerce\Modules\Search\Contracts\SearchIndex;
use Litecommerce\Modules\Search\Infrastructure\DatabaseProductSearchEngine;
use Litecommerce\Modules\Search\Infrastructure\DatabaseSearchIndex;
use Litecommerce\Modules\Warehouse\Contracts\Events\StockLevelChanged;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SearchIndex::class, DatabaseSearchIndex::class);
        $this->app->bind(ProductSearchEngine::class, DatabaseProductSearchEngine::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'search');

        Event::listen(CatalogContentChanged::class, fn () => $this->app->make(SearchIndex::class)->markDirty());
        Event::listen(PricingIndexChanged::class, function (PricingIndexChanged $event): void {
            $index = $this->app->make(SearchIndex::class);

            if ($event->variantIds === []) {
                $index->markDirty();
            } else {
                // Availability now depends on having a price (P2-012), not
                // only on stock/enabled, so a price change must refresh
                // both projections for the same variants.
                $index->refreshPrices($event->variantIds);
                $index->refreshAvailability($event->variantIds);
            }
        });
        Event::listen(StockLevelChanged::class, fn (StockLevelChanged $event) => $this->app->make(SearchIndex::class)->refreshAvailability($event->variantIds));

        if ($this->app->runningInConsole()) {
            $this->commands([RebuildSearchIndexCommand::class]);
        }
    }
}
