<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Cache\Infrastructure\LaravelObjectCache;
use Litecommerce\Modules\Cache\Presentation\Console\CacheStatusCommand;
use Litecommerce\Modules\Cache\Presentation\Console\ClearApplicationCacheCommand;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LaravelObjectCache::class, fn (Application $app): LaravelObjectCache => new LaravelObjectCache(
            $app->make(CacheManager::class),
            (int) config('object-cache.default_ttl'),
            (int) config('object-cache.max_ttl'),
            (int) config('object-cache.generation_ttl'),
        ));
        $this->app->alias(LaravelObjectCache::class, ObjectCache::class);
        $this->app->alias(LaravelObjectCache::class, CacheMaintenance::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'cache');
        $this->commands([CacheStatusCommand::class, ClearApplicationCacheCommand::class]);
    }
}
