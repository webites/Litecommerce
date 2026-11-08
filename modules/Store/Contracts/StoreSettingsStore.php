<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

interface StoreSettingsStore
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    /** @return array<string, mixed> */
    public function all(?string $prefix = null): array;

    public function set(string $key, mixed $value): void;

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void;

    public function remove(string $key): bool;
}
