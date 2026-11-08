<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Console;

use Illuminate\Console\Command;
use Litecommerce\Modules\Email\Application\ProcessEmailQueue;

final class ProcessEmailQueueCommand extends Command
{
    protected $signature = 'email:process {--limit= : Maximum messages} {--time-limit= : Maximum seconds}';

    protected $description = 'Process a bounded batch of queued emails';

    public function handle(ProcessEmailQueue $processor): int
    {
        $count = $processor->process(
            (int) ($this->option('limit') ?: config('email.cron_batch', 25)),
            (int) ($this->option('time-limit') ?: config('email.cron_time_limit', 50)),
        );
        $this->info("Processed {$count} email message(s).");

        return self::SUCCESS;
    }
}
