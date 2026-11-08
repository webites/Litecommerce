<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Illuminate\Support\Facades\Artisan;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;
use Throwable;

final class RunMigrationsStep extends BaseUpdateStep
{
    public function key(): string
    {
        return 'migrate';
    }

    public function isIrreversible(): bool
    {
        return true;
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $exception) {
            throw new UpdateStepFailedException('migration_failed', $exception->getMessage());
        }

        if ($exitCode !== 0) {
            throw new UpdateStepFailedException('migration_failed', trim(Artisan::output()));
        }
    }
}
