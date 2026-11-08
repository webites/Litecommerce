<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateProgress;

final class CompleteUpdateStep extends BaseUpdateStep
{
    public function __construct(private readonly MaintenanceMode $maintenance, private readonly AuditLogger $audit) {}

    public function key(): string
    {
        return 'complete';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        if ($this->maintenance->state()?->reason === MaintenanceReason::Update) {
            $this->maintenance->disable();
        }

        $this->audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: $state->actorId,
            action: 'updates.completed',
            subjectType: 'core',
            subjectId: $state->targetVersion,
            changes: ['version' => $state->targetVersion, 'operation' => $state->id],
        ));
    }
}
