<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Scheduler\Application\RecordSystemCronSignal;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;

final class RunScheduledTasksCommand extends Command
{
    protected $signature = 'scheduler:run {--limit= : Maximum tasks} {--time-limit= : Maximum seconds} {--force : Run outside system mode}';

    protected $description = 'Run due Litecommerce scheduled tasks';

    public function handle(RunDueScheduledTasks $runner, RecordSystemCronSignal $signal): int
    {
        if (! $this->option('force')) {
            $signal->record();
        }

        $mode = (string) config('scheduler.mode', 'traffic');
        if ($mode !== 'system' && ! $this->option('force')) {
            $this->components->info("Scheduler mode is [{$mode}]; no system-cron work was performed.");

            return self::SUCCESS;
        }

        $result = $runner->run(
            (int) ($this->option('limit') ?: config('scheduler.system_limit', 20)),
            (int) ($this->option('time-limit') ?: config('scheduler.system_time_limit', 50)),
        );
        $this->components->info("Processed {$result->processed} scheduled task(s); {$result->failed} failed.");

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
