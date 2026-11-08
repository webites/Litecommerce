<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure;

use Litecommerce\Modules\Identity\Application\PermissionResolver;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;

final class EloquentStaffPermissionChecker implements StaffPermissionChecker
{
    /** @var array<int, StaffAccount|null> */
    private array $accounts = [];

    public function __construct(private readonly PermissionResolver $resolver) {}

    public function allows(int $staffAccountId, string $permission): bool
    {
        $account = $this->accounts[$staffAccountId]
            ??= StaffAccount::query()->find($staffAccountId);

        if ($account === null) {
            return false;
        }

        /** @var array<string, bool> $overrides */
        $overrides = $account->permission_overrides ?? [];

        return $this->resolver->allows(
            (string) $account->getRawOriginal('role'),
            $overrides,
            $permission,
        );
    }
}
