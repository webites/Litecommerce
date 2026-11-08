<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Contracts;

final readonly class MaintenanceState
{
    public function __construct(
        public MaintenanceReason $reason,
        public \DateTimeImmutable $enabledAt,
        public ?string $message,
        public int $retryAfterSeconds,
        public ?string $actorId,
    ) {}

    /** An update replaces code and schema, so scheduled work must not run meanwhile. */
    public function pausesBackgroundTasks(): bool
    {
        return $this->reason === MaintenanceReason::Update;
    }
}
