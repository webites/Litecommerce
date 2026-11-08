<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Infrastructure;

use Closure;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use InvalidArgumentException;
use JsonException;
use Litecommerce\Modules\Cache\Contracts\CacheMaintenance;
use Litecommerce\Modules\Cache\Contracts\CacheStatusData;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Throwable;

final class LaravelObjectCache implements CacheMaintenance, ObjectCache
{
    private const GLOBAL_GENERATION_KEY = 'litecommerce:object-cache:generation';

    public function __construct(
        private readonly CacheManager $cache,
        private readonly int $defaultTtl,
        private readonly int $maxTtl,
        private readonly int $generationTtl,
    ) {
        if ($this->defaultTtl < 1 || $this->maxTtl < $this->defaultTtl || $this->generationTtl <= $this->maxTtl) {
            throw new InvalidArgumentException('Object cache TTL configuration is invalid.');
        }
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $this->assertGroup($group);
        $this->assertKey($key);

        try {
            $value = $this->memo()->get($this->itemKey($group, $key));
        } catch (Throwable) {
            return $default;
        }

        return $value === null ? $default : $value;
    }

    public function put(string $group, string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->assertGroup($group);
        $this->assertKey($key);

        if ($value === null) {
            throw new InvalidArgumentException('Null is a cache miss marker and cannot be stored.');
        }

        $this->assertCacheable($value);
        $ttl = $this->ttl($ttlSeconds);

        try {
            $this->memo()->put($this->itemKey($group, $key), $value, $ttl);
        } catch (Throwable) {
        }
    }

    public function remember(string $group, string $key, Closure $loader, ?int $ttlSeconds = null): mixed
    {
        $this->assertGroup($group);
        $this->assertKey($key);
        $ttl = $this->ttl($ttlSeconds);
        $itemKey = null;

        try {
            $itemKey = $this->itemKey($group, $key);
            $cached = $this->memo()->get($itemKey);

            if ($cached !== null) {
                return $cached;
            }
        } catch (Throwable) {
            $itemKey = null;
        }

        $value = $loader();

        if ($value !== null) {
            $this->assertCacheable($value);

            if ($itemKey !== null) {
                try {
                    $this->memo()->put($itemKey, $value, $ttl);
                } catch (Throwable) {
                }
            }
        }

        return $value;
    }

    public function forget(string $group, string $key): void
    {
        $this->assertGroup($group);
        $this->assertKey($key);

        try {
            $this->memo()->forget($this->itemKey($group, $key));
        } catch (Throwable) {
        }
    }

    public function invalidateGroup(string $group): void
    {
        $this->assertGroup($group);

        try {
            $this->memo()->put($this->groupGenerationKey($this->globalGeneration(), $group), $this->token(), $this->generationTtl);
        } catch (Throwable) {
        }
    }

    public function clearApplicationCache(): void
    {
        try {
            $this->memo()->put(self::GLOBAL_GENERATION_KEY, $this->token(), $this->generationTtl);
        } catch (Throwable) {
        }
    }

    public function status(): CacheStatusData
    {
        $repository = $this->persistent();
        $store = (string) ($repository->getName() ?? config('cache.default', 'unknown'));
        $probeKey = 'litecommerce:object-cache:probe:'.$this->token();
        $available = false;

        try {
            $available = $repository->put($probeKey, 'ok', 10) && $repository->get($probeKey) === 'ok';
        } catch (Throwable) {
            $available = false;
        } finally {
            try {
                $repository->forget($probeKey);
            } catch (Throwable) {
            }
        }

        return new CacheStatusData(
            store: $store,
            available: $available,
            supportsLocks: $repository->getStore() instanceof LockProvider,
            supportsTags: method_exists($repository, 'supportsTags') && $repository->supportsTags(),
        );
    }

    private function itemKey(string $group, string $key): string
    {
        $this->assertGroup($group);
        $this->assertKey($key);
        $global = $this->globalGeneration();
        $groupGeneration = $this->generation($this->groupGenerationKey($global, $group));

        return "litecommerce:object-cache:item:{$global}:{$group}:{$groupGeneration}:".hash('sha256', $key);
    }

    private function globalGeneration(): string
    {
        return $this->generation(self::GLOBAL_GENERATION_KEY);
    }

    private function generation(string $key): string
    {
        $generation = $this->memo()->get($key);

        if (is_string($generation) && $generation !== '') {
            return $generation;
        }

        $repository = $this->persistent();
        $repository->add($key, $this->token(), $this->generationTtl);
        $generation = $repository->get($key);

        if (! is_string($generation) || $generation === '') {
            throw new InvalidArgumentException('Cache store did not persist a generation token.');
        }

        $this->memo()->put($key, $generation, $this->generationTtl);

        return $generation;
    }

    private function groupGenerationKey(string $global, string $group): string
    {
        return "litecommerce:object-cache:group:{$global}:".hash('sha256', $group);
    }

    private function ttl(?int $ttlSeconds): int
    {
        $ttl = $ttlSeconds ?? $this->defaultTtl;

        if ($ttl < 1 || $ttl > $this->maxTtl) {
            throw new InvalidArgumentException("Object cache TTL must be between 1 and {$this->maxTtl} seconds.");
        }

        return $ttl;
    }

    private function assertGroup(string $group): void
    {
        if (strlen($group) > 191 || preg_match('/^(core|plugin|theme)\.[a-z0-9][a-z0-9._-]+$/', $group) !== 1) {
            throw new InvalidArgumentException("Invalid object cache group [{$group}].");
        }
    }

    private function assertKey(string $key): void
    {
        if ($key === '' || strlen($key) > 512 || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
            throw new InvalidArgumentException('Object cache key must be non-empty, at most 512 bytes, and contain no control characters.');
        }
    }

    private function assertCacheable(mixed $value): void
    {
        if (! is_scalar($value) && ! is_array($value)) {
            throw new InvalidArgumentException('Object cache values must be scalars or arrays.');
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Object cache value must be JSON-serializable.', previous: $exception);
        }

        if (is_array($value)) {
            array_walk_recursive($value, static function (mixed $item): void {
                if ($item !== null && ! is_scalar($item)) {
                    throw new InvalidArgumentException('Object cache arrays may contain only null and scalar values.');
                }
            });
        }
    }

    private function token(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function memo(): Repository
    {
        return $this->cache->memo();
    }

    private function persistent(): Repository
    {
        return $this->cache->store();
    }
}
