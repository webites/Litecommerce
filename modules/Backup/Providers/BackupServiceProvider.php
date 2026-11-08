<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Backup\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Backup\Application\CreateBackup;
use Litecommerce\Modules\Backup\Application\VerifyBackup;
use Litecommerce\Modules\Backup\Console\CreateBackupCommand;
use Litecommerce\Modules\Backup\Console\VerifyBackupCommand;
use Litecommerce\Modules\Backup\Contracts\BackupManager;
use Litecommerce\Modules\Backup\Contracts\BackupVerifier;

final class BackupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/backup.php', 'backup');

        $this->app->bind(BackupManager::class, CreateBackup::class);
        $this->app->bind(BackupVerifier::class, VerifyBackup::class);
    }

    public function boot(): void
    {
        $this->commands([CreateBackupCommand::class, VerifyBackupCommand::class]);
    }
}
