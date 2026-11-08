<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

final readonly class ScheduledRunResult
{
    public function __construct(
        public int $processed,
        public int $failed,
    ) {}
}
