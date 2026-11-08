<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cache\Contracts;

use Closure;

interface ObjectCache
{
    public function get(string $group, string $key, mixed $default = null): mixed;

    public function put(string $group, string $key, mixed $value, ?int $ttlSeconds = null): void;

    public function remember(string $group, string $key, Closure $loader, ?int $ttlSeconds = null): mixed;

    public function forget(string $group, string $key): void;

    public function invalidateGroup(string $group): void;
}
