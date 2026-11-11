<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Application;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatus;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerStatusData;
use Litecommerce\Modules\Scheduler\Contracts\SchedulerTaskStatus;
use Litecommerce\Modules\Scheduler\Infrastructure\Models\ScheduledTask;

final class ReadSchedulerStatus implements SchedulerStatus
{
    public function __construct(private readonly ScheduledTaskRegistry $registry) {}

    public function current(): SchedulerStatusData
    {
        $mode = (string) config('scheduler.mode', 'traffic');
        $definitions = [];
        foreach ($this->registry->all() as $definition) {
            $definitions[$definition->key] = $definition;
        }
        $keys = array_keys($definitions);
        $rows = ScheduledTask::query()->whereIn('key', $keys)->orderBy('key')->get()->keyBy('key');

        $tasks = [];
        $lastRunAt = null;
        foreach ($keys as $key) {
            $row = $rows->get($key);
            if (! $row instanceof ScheduledTask) {
                continue;
            }
            $tasks[] = new SchedulerTaskStatus(
                $key,
                $this->nameOf($definitions[$key]->label, $key),
                $row->interval_seconds,
                $row->status,
                $this->immutable($row->next_run_at),
                $this->immutable($row->last_succeeded_at),
                $this->immutable($row->last_failed_at),
                $row->consecutive_failures,
                $row->last_error_code,
                $row->status === 'idle' && $row->next_run_at->lt(now()->subSeconds(max(120, $row->interval_seconds * 2))),
            );
            if ($row->last_finished_at !== null && ($lastRunAt === null || $row->last_finished_at->gt($lastRunAt))) {
                $lastRunAt = $row->last_finished_at;
            }
        }
        usort($tasks, static fn (SchedulerTaskStatus $a, SchedulerTaskStatus $b): int => strcmp($a->key, $b->key));

        $cronSeen = DB::table('scheduler_signals')->where('name', RecordSystemCronSignal::NAME)->value('seen_at');

        return new SchedulerStatusData(
            $mode,
            $this->health($mode, $tasks, $lastRunAt),
            $this->immutable($lastRunAt),
            $tasks,
            $cronSeen === null ? null : Carbon::parse($cronSeen)->toDateTimeImmutable(),
        );
    }

    private function nameOf(string $label, string $key): string
    {
        if ($label === '') {
            return $key;
        }

        return Lang::has($label) ? (string) trans($label) : $label;
    }

    /** @param list<SchedulerTaskStatus> $tasks */
    private function health(string $mode, array $tasks, ?Carbon $lastRunAt): string
    {
        if ($mode === 'off') {
            return SchedulerStatusData::HEALTH_DISABLED;
        }
        if ($lastRunAt === null) {
            return SchedulerStatusData::HEALTH_PENDING;
        }
        foreach ($tasks as $task) {
            if ($task->consecutiveFailures > 0) {
                return SchedulerStatusData::HEALTH_FAILING;
            }
        }

        $staleAfter = $mode === 'system' ? 180 : 900;

        return $lastRunAt->lt(now()->subSeconds($staleAfter))
            ? SchedulerStatusData::HEALTH_DELAYED
            : SchedulerStatusData::HEALTH_OK;
    }

    private function immutable(?Carbon $value): ?DateTimeImmutable
    {
        return $value?->toDateTimeImmutable();
    }
}
