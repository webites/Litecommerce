<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Application;

use Litecommerce\Modules\Identity\Contracts\PermissionRegistry;

final readonly class PermissionResolver
{
    public function __construct(private PermissionRegistry $registry) {}

    /** @param array<string, bool> $overrides */
    public function allows(string $role, array $overrides, string $permission): bool
    {
        if (array_key_exists($permission, $overrides)) {
            return $overrides[$permission];
        }

        $definition = $this->registry->find($permission);

        return $definition !== null
            && in_array($role, $definition->grantedToRoles, true);
    }
}
