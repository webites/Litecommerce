<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Application;

use Illuminate\Support\Facades\DB;
use Throwable;

final class RecordSystemCronSignal
{
    public const NAME = 'system_cron';

    public function record(): void
    {
        try {
            DB::table('scheduler_signals')->upsert([['name' => self::NAME, 'seen_at' => now()]], ['name'], ['seen_at']);
        } catch (Throwable) {
            // The signal is diagnostic only and must never break the cron run.
        }
    }
}
