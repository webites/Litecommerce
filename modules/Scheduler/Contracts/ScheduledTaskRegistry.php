<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

interface ScheduledTaskRegistry
{
    public function register(string $key, int $intervalSeconds, callable $handler): void;

    /** @return list<ScheduledTaskDefinition> */
    public function all(): array;
}
