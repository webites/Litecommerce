<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatus;

final class SchedulerStatusCommand extends Command
{
    protected $signature = 'scheduler:status';

    protected $description = 'Show Litecommerce scheduler mode, health and registered tasks';

    public function handle(SchedulerStatus $status): int
    {
        $current = $status->current();
        $this->components->info("Mode: {$current->mode}; health: {$current->health}; last run: ".($current->lastRunAt?->format('Y-m-d H:i:s') ?? 'never'));
        $this->table(
            ['Task', 'Every (s)', 'Status', 'Next run', 'Failures', 'Overdue'],
            array_map(static fn ($task): array => [
                $task->key,
                $task->intervalSeconds,
                $task->status,
                $task->nextRunAt?->format('Y-m-d H:i:s') ?? '-',
                $task->consecutiveFailures,
                $task->overdue ? 'yes' : 'no',
            ], $current->tasks),
        );

        return $current->health === 'failing' ? self::FAILURE : self::SUCCESS;
    }
}
