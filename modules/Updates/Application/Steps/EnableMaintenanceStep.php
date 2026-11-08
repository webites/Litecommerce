<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;

final class EnableMaintenanceStep extends BaseUpdateStep
{
    public function __construct(private readonly MaintenanceMode $maintenance) {}

    public function key(): string
    {
        return 'maintenance_on';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        if ($this->maintenance->enable(MaintenanceReason::Update, null, $state->actorId)) {
            $state->maintenanceEnabledByUpdate = true;

            return;
        }

        if (! $state->maintenanceEnabledByUpdate && $this->maintenance->state()?->reason !== MaintenanceReason::Update) {
            throw new UpdateStepFailedException('maintenance_already_active', 'Maintenance mode was enabled manually; turn it off before updating.');
        }
    }
}
