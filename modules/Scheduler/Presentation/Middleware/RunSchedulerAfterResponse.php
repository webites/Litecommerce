<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RunSchedulerAfterResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ((string) config('scheduler.mode', 'traffic') !== 'traffic' || $response->getStatusCode() >= 500 || app()->runningInConsole()) {
            return;
        }

        try {
            $interval = max(1, (int) config('scheduler.traffic_interval_seconds', 30));
            if (! Cache::add('scheduler:traffic:throttle', true, $interval)) {
                return;
            }
            if (! Schema::hasTable('scheduled_tasks')) {
                return;
            }
            Cache::lock('scheduler:traffic:lock', max(5, $interval))->get(function (): void {
                app(RunDueScheduledTasks::class)->run(
                    (int) config('scheduler.traffic_limit', 2),
                    (int) config('scheduler.traffic_time_limit', 3),
                );
            });
        } catch (Throwable $exception) {
            Log::warning('Traffic-driven scheduler failed.', ['error_code' => $exception::class]);
        }
    }
}
