<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

final class UpdateOperationState
{
    public const RUNNING = 'running';

    public const FAILED = 'failed';

    public const ROLLED_BACK = 'rolled_back';

    public const COMPLETED = 'completed';

    /**
     * @param  array<string, mixed>  $release  serialized ReleaseInfo
     * @param  array<string, array{status: string, message: ?string}>  $steps
     * @param  array<string, string>  $journal  path => original state ('present' has a copy in the file backup, 'absent' did not exist)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $targetVersion,
        public readonly array $release,
        public readonly ?string $actorId,
        public readonly string $startedAt,
        public string $status = self::RUNNING,
        public array $steps = [],
        public bool $irreversible = false,
        public bool $maintenanceEnabledByUpdate = false,
        public array $journal = [],
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?string $finishedAt = null,
        public ?string $databaseBackup = null,
    ) {}

    public function isFinished(): bool
    {
        return in_array($this->status, [self::COMPLETED, self::ROLLED_BACK], true);
    }

    public function isDone(string $step): bool
    {
        return ($this->steps[$step]['status'] ?? null) === 'done';
    }

    public function mark(string $step, string $status, ?string $message = null): void
    {
        $this->steps[$step] = ['status' => $status, 'message' => $message];
    }
}
