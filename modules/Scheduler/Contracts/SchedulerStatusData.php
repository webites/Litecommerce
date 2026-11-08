<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

use DateTimeImmutable;

final readonly class SchedulerStatusData
{
    public const HEALTH_OK = 'ok';

    public const HEALTH_PENDING = 'pending';

    public const HEALTH_DELAYED = 'delayed';

    public const HEALTH_FAILING = 'failing';

    public const HEALTH_DISABLED = 'disabled';

    /** @param list<SchedulerTaskStatus> $tasks */
    public function __construct(
        public string $mode,
        public string $health,
        public ?DateTimeImmutable $lastRunAt,
        public array $tasks,
        public ?DateTimeImmutable $systemCronSeenAt = null,
    ) {}
}
