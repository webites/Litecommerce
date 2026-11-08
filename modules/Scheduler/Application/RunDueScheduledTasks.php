<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledRunResult;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskDefinition;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Scheduler\Infrastructure\Models\ScheduledTask;
use Throwable;

final class RunDueScheduledTasks
{
    public function __construct(private readonly ScheduledTaskRegistry $registry, private readonly MaintenanceMode $maintenance) {}

    public function run(int $limit, int $timeLimitSeconds): ScheduledRunResult
    {
        $definitions = $this->definitions();
        if ($definitions === [] || $this->maintenance->state()?->pausesBackgroundTasks()) {
            return new ScheduledRunResult(0, 0);
        }

        $this->synchronize($definitions);
        $started = microtime(true);
        $processed = 0;
        $failed = 0;

        while ($processed < max(1, $limit) && microtime(true) - $started < max(1, $timeLimitSeconds)) {
            $claimed = $this->claim(array_keys($definitions));
            if ($claimed === null) {
                break;
            }

            [$task, $token] = $claimed;
            try {
                ($definitions[$task->key]->handler)();
                $this->finish($task, $token, true);
            } catch (Throwable $exception) {
                $failed++;
                $this->finish($task, $token, false, $exception);
                Log::warning('Scheduled task failed.', [
                    'task' => $task->key,
                    'error_code' => $exception::class,
                ]);
            }
            $processed++;
        }

        return new ScheduledRunResult($processed, $failed);
    }

    /** @return array<string, ScheduledTaskDefinition> */
    private function definitions(): array
    {
        $definitions = [];
        foreach ($this->registry->all() as $definition) {
            $definitions[$definition->key] = $definition;
        }

        return $definitions;
    }

    /** @param array<string, ScheduledTaskDefinition> $definitions */
    private function synchronize(array $definitions): void
    {
        $now = now();
        foreach ($definitions as $definition) {
            DB::table('scheduled_tasks')->insertOrIgnore([
                'key' => $definition->key,
                'interval_seconds' => $definition->intervalSeconds,
                'status' => 'idle',
                'next_run_at' => $now,
                'run_count' => 0,
                'consecutive_failures' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('scheduled_tasks')->where('key', $definition->key)->update([
                'interval_seconds' => $definition->intervalSeconds,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param list<string> $keys
     * @return array{0: ScheduledTask, 1: string}|null
     */
    private function claim(array $keys): ?array
    {
        return DB::transaction(function () use ($keys): ?array {
            $task = ScheduledTask::query()
                ->whereIn('key', $keys)
                ->where('next_run_at', '<=', now())
                ->where(function ($query): void {
                    $query->where('status', 'idle')
                        ->orWhere(function ($expired): void {
                            $expired->where('status', 'running')->where('leased_until', '<', now());
                        });
                })
                ->orderBy('next_run_at')
                ->orderBy('key')
                ->lockForUpdate()
                ->first();
            if (! $task instanceof ScheduledTask) {
                return null;
            }

            $token = (string) Str::uuid();
            $task->update([
                'status' => 'running',
                'lease_token' => $token,
                'leased_until' => now()->addSeconds(max(30, (int) config('scheduler.lease_seconds', 120))),
                'last_started_at' => now(),
            ]);

            return [$task, $token];
        });
    }

    private function finish(ScheduledTask $task, string $token, bool $succeeded, ?Throwable $exception = null): void
    {
        $delay = $succeeded
            ? $task->interval_seconds
            : max(60, (int) config('scheduler.failure_retry_seconds', 60));

        $values = [
            'status' => 'idle',
            'next_run_at' => now()->addSeconds($delay),
            'lease_token' => null,
            'leased_until' => null,
            'last_finished_at' => now(),
            'run_count' => $task->run_count + 1,
            'consecutive_failures' => $succeeded ? 0 : $task->consecutive_failures + 1,
            'last_error_code' => $succeeded || $exception === null ? null : mb_substr($exception::class, 0, 191),
            'updated_at' => now(),
        ];
        $values[$succeeded ? 'last_succeeded_at' : 'last_failed_at'] = now();

        ScheduledTask::query()
            ->whereKey($task->key)
            ->where('status', 'running')
            ->where('lease_token', $token)
            ->update($values);
    }
}
