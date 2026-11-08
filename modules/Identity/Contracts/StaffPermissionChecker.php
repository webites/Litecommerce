<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

interface StaffPermissionChecker
{
    public function allows(int $staffAccountId, string $permission): bool;
}
