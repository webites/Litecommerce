<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Backup\Contracts\BackupFailedException;
use Litecommerce\Modules\Backup\Contracts\BackupManager;
use Litecommerce\Modules\Backup\Contracts\BackupRequest;
use Litecommerce\Modules\Backup\Contracts\BackupTarget;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;

final class BackupDatabaseStep extends BaseUpdateStep
{
    public function __construct(private readonly BackupManager $backups) {}

    public function key(): string
    {
        return 'backup_database';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        if ($state->databaseBackup !== null && is_file($state->databaseBackup)) {
            return;
        }

        try {
            $state->databaseBackup = $this->backups->create(new BackupRequest([BackupTarget::Database], label: 'before-'.$state->targetVersion))->path;
        } catch (BackupFailedException $exception) {
            throw new UpdateStepFailedException('backup_failed', $exception->getMessage());
        }
    }
}
