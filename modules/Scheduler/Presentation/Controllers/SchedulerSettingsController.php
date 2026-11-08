<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Scheduler\Contracts\CronConfigurator;
use Litecommerce\Modules\Scheduler\Contracts\CronSetupResult;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatus;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerTaskStatus;
use Litecommerce\Modules\Store\Contracts\SettingsTabContent;
use Litecommerce\Modules\Store\Contracts\SettingsTabContext;

final class SchedulerSettingsController extends Controller
{
    public function __construct(
        private readonly SchedulerStatus $status,
        private readonly CronConfigurator $cron,
    ) {}

    public function settingsTab(SettingsTabContext $context): SettingsTabContent
    {
        $current = $this->status->current();

        return SettingsTabContent::component('scheduler', [
            'mode' => $current->mode,
            'health' => $current->health,
            'last_run_at' => $current->lastRunAt?->format(DATE_ATOM),
            'system_cron_seen_at' => $current->systemCronSeenAt?->format(DATE_ATOM),
            'cron_command' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
            'can_configure_cron' => $this->cron->canAttempt(),
            'tasks' => array_map(static fn (SchedulerTaskStatus $task): array => [
                'key' => $task->key,
                'interval_seconds' => $task->intervalSeconds,
                'status' => $task->status,
                'next_run_at' => $task->nextRunAt?->format(DATE_ATOM),
                'last_succeeded_at' => $task->lastSucceededAt?->format(DATE_ATOM),
                'last_failed_at' => $task->lastFailedAt?->format(DATE_ATOM),
                'consecutive_failures' => $task->consecutiveFailures,
                'last_error_code' => $task->lastErrorCode,
                'overdue' => $task->overdue,
            ], $current->tasks),
        ]);
    }

    public function configureCron(Request $request, AuditLogger $audit): RedirectResponse
    {
        $result = $this->cron->install();

        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: 'scheduler.cron.configure_attempted',
            subjectType: 'scheduler',
            metadata: ['result' => $result->status, 'reason' => $result->reason],
        ));

        return match ($result->status) {
            CronSetupResult::INSTALLED => back()->with('status', trans('scheduler::panel.cron_installed')),
            CronSetupResult::ALREADY_INSTALLED => back()->with('status', trans('scheduler::panel.cron_already_installed')),
            default => back()->with('error', trans('scheduler::panel.cron_failed', ['reason' => trans('scheduler::panel.cron_reasons.'.($result->reason ?? 'unknown'))])),
        };
    }
}
