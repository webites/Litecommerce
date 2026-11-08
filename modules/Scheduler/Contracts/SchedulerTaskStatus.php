<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

use DateTimeImmutable;

final readonly class SchedulerTaskStatus
{
    public function __construct(
        public string $key,
        public int $intervalSeconds,
        public string $status,
        public ?DateTimeImmutable $nextRunAt,
        public ?DateTimeImmutable $lastSucceededAt,
        public ?DateTimeImmutable $lastFailedAt,
        public int $consecutiveFailures,
        public ?string $lastErrorCode,
        public bool $overdue,
    ) {}
}
