<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

final readonly class PermissionDefinition
{
    /** @param list<string> $grantedToRoles */
    public function __construct(
        public string $key,
        public string $label,
        public string $group,
        public array $grantedToRoles = [],
    ) {}
}
