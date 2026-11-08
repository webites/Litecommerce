<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Scheduler\Application\ConfigureHostingCron;
use Litecommerce\Modules\Scheduler\Application\ReadSchedulerStatus;
use Litecommerce\Modules\Scheduler\Console\RunScheduledTasksCommand;
use Litecommerce\Modules\Scheduler\Console\SchedulerStatusCommand;
use Litecommerce\Modules\Scheduler\Contracts\CronConfigurator;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatus;
use Litecommerce\Modules\Scheduler\Infrastructure\InMemoryScheduledTaskRegistry;
use Litecommerce\Modules\Scheduler\Infrastructure\ProcessRunner;
use Litecommerce\Modules\Scheduler\Infrastructure\ProcOpenProcessRunner;
use Litecommerce\Modules\Scheduler\Presentation\Controllers\SchedulerSettingsController;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;

final class SchedulerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/scheduler.php', 'scheduler');
        $this->app->singleton(ScheduledTaskRegistry::class, InMemoryScheduledTaskRegistry::class);
        $this->app->bind(SchedulerStatus::class, ReadSchedulerStatus::class);
        $this->app->bind(ProcessRunner::class, ProcOpenProcessRunner::class);
        $this->app->bind(CronConfigurator::class, ConfigureHostingCron::class);
        $this->commands([RunScheduledTasksCommand::class, SchedulerStatusCommand::class]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'scheduler');

        $this->app->make(SettingsTabRegistry::class)->register(
            'scheduler',
            'scheduler::panel.tab',
            800,
            [$this->app->make(SchedulerSettingsController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageStoreSettings->value),
        );
    }
}
