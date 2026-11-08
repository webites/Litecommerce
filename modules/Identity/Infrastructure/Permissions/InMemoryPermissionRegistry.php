<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Permissions;

use InvalidArgumentException;
use Litecommerce\Modules\Identity\Contracts\PermissionDefinition;
use Litecommerce\Modules\Identity\Contracts\PermissionRegistry;

final class InMemoryPermissionRegistry implements PermissionRegistry
{
    /** @var array<string, PermissionDefinition> */
    private array $definitions = [];

    public function register(PermissionDefinition $definition): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+$/', $definition->key)) {
            throw new InvalidArgumentException("Nieprawidłowy klucz uprawnienia [{$definition->key}]. Użyj przestrzeni nazw, np. vendor.plugin.action.");
        }

        if (isset($this->definitions[$definition->key])) {
            throw new InvalidArgumentException("Uprawnienie [{$definition->key}] jest już zarejestrowane.");
        }

        $this->definitions[$definition->key] = $definition;
    }

    public function find(string $key): ?PermissionDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function all(): array
    {
        return $this->definitions;
    }
}
