<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface PermissionRegistry
{
    public function register(PermissionDefinition $definition): void;

    public function find(string $key): ?PermissionDefinition;

    /** @return array<string, PermissionDefinition> */
    public function all(): array;
}
