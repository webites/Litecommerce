<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

final readonly class UpdateOperationData
{
    /** @param array<string, array{status: string, message: ?string}> $steps */
    public function __construct(
        public string $id,
        public string $targetVersion,
        public string $status,
        public array $steps,
        public ?string $errorCode,
        public ?string $errorMessage,
        public string $startedAt,
        public ?string $finishedAt,
    ) {}

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'rolled_back'], true);
    }
}
