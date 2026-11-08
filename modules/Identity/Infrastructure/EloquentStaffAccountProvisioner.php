<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure;

use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Identity\Contracts\AdministratorData;
use Litecommerce\Modules\Identity\Contracts\StaffAccountProvisioner;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;

final class EloquentStaffAccountProvisioner implements StaffAccountProvisioner
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function ensureAdministratorExists(AdministratorData $administrator): void
    {
        $account = StaffAccount::query()->firstOrCreate(
            ['email' => $administrator->email],
            [
                'name' => $administrator->name,
                'password' => $administrator->hashedPassword,
                'role' => SystemStaffRole::Administrator,
                'permission_overrides' => [],
            ],
        );

        if ($account->wasRecentlyCreated) {
            $this->auditLogger->record(new AuditEntryData(
                actorType: 'system',
                actorId: null,
                action: 'identity.staff.bootstrap_created',
                subjectType: 'staff_account',
                subjectId: (string) $account->getKey(),
                changes: ['email' => $account->email, 'role' => SystemStaffRole::Administrator->value],
            ));
        }
    }
}
