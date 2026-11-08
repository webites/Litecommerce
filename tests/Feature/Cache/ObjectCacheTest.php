<?php

declare(strict_types=1);

namespace Tests\Feature\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Cache\Infrastructure\LaravelObjectCache;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ObjectCacheTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
    }

    public function test_remember_reuses_value_and_group_invalidation_reloads_it(): void
    {
        $cache = app(ObjectCache::class);
        $loads = 0;
        $loader = function () use (&$loads): array {
            $loads++;

            return ['load' => $loads];
        };

        $this->assertSame(['load' => 1], $cache->remember('core.test.values', 'one', $loader));
        $this->assertSame(['load' => 1], $cache->remember('core.test.values', 'one', $loader));

        $cache->invalidateGroup('core.test.values');

        $this->assertSame(['load' => 2], $cache->remember('core.test.values', 'one', $loader));
    }

    public function test_global_clear_rotates_application_cache_without_flushing_foreign_keys(): void
    {
        $cache = app(ObjectCache::class);
        $cache->put('core.test.values', 'one', 'cached');
        Cache::put('scheduler:traffic:lock', 'preserved', 300);
        Cache::put('foreign-package:key', 'preserved', 300);

        app(CacheMaintenance::class)->clearApplicationCache();

        $this->assertNull($cache->get('core.test.values', 'one'));
        $this->assertSame('preserved', Cache::get('scheduler:traffic:lock'));
        $this->assertSame('preserved', Cache::get('foreign-package:key'));
    }

    public function test_contract_works_with_array_database_and_file_stores(): void
    {
        $this->artisan('migrate', ['--force' => true]);

        foreach (['array', 'database', 'file'] as $store) {
            config(['cache.default' => $store]);
            $manager = app(CacheManager::class);
            $manager->forgetDriver($store);
            $cache = new LaravelObjectCache($manager, 60, 3600, 7200);
            $cache->put('core.test.drivers', $store, ['store' => $store]);

            $this->assertSame(['store' => $store], $cache->get('core.test.drivers', $store));
            $this->assertTrue($cache->status()->available);
        }
    }

    public function test_invalid_group_ttl_and_object_values_are_rejected(): void
    {
        $cache = app(ObjectCache::class);

        foreach ([
            fn () => $cache->put('invalid', 'key', 'value'),
            fn () => $cache->put('core.test.values', 'key', 'value', 0),
            fn () => $cache->put('core.test.values', 'key', new \stdClass),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected invalid object cache input to be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_unavailable_store_fails_open_and_loader_still_returns_business_data(): void
    {
        config(['cache.default' => 'null']);
        $manager = app(CacheManager::class);
        $manager->forgetDriver('null');
        $cache = new LaravelObjectCache($manager, 60, 3600, 7200);
        $loads = 0;

        $first = $cache->remember('core.test.failure', 'business-data', function () use (&$loads): array {
            $loads++;

            return ['available' => true];
        });
        $second = $cache->remember('core.test.failure', 'business-data', function () use (&$loads): array {
            $loads++;

            return ['available' => true];
        });

        $this->assertSame(['available' => true], $first);
        $this->assertSame(['available' => true], $second);
        $this->assertSame(2, $loads);
        $this->assertSame('fallback', $cache->get('core.test.failure', 'missing', 'fallback'));
        $cache->put('core.test.failure', 'ignored', 'value');
        $cache->invalidateGroup('core.test.failure');
        $cache->clearApplicationCache();
    }
}
