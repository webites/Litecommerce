<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

use Closure;

final readonly class ScheduledTaskDefinition
{
    public function __construct(
        public string $key,
        public int $intervalSeconds,
        public Closure $handler,
    ) {}
}
