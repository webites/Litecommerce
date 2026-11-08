<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Infrastructure;

use Closure;
use InvalidArgumentException;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskDefinition;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class InMemoryScheduledTaskRegistry implements ScheduledTaskRegistry
{
    /** @var array<string, ScheduledTaskDefinition> */
    private array $tasks = [];

    public function register(string $key, int $intervalSeconds, callable $handler): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9_-]+)+$/', $key) !== 1 || mb_strlen($key) > 191) {
            throw new InvalidArgumentException("Scheduled task key [{$key}] is invalid.");
        }
        if ($intervalSeconds < 60) {
            throw new InvalidArgumentException('Scheduled task interval must be at least 60 seconds.');
        }
        if (isset($this->tasks[$key])) {
            throw new InvalidArgumentException("Scheduled task [{$key}] is already registered.");
        }

        $this->tasks[$key] = new ScheduledTaskDefinition(
            $key,
            $intervalSeconds,
            $handler instanceof Closure ? $handler : Closure::fromCallable($handler),
        );
    }

    public function all(): array
    {
        return array_values($this->tasks);
    }
}
