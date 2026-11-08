<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Contracts;

final readonly class AuditEntryData
{
    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $actorType,
        public ?string $actorId,
        public string $action,
        public string $subjectType,
        public ?string $subjectId = null,
        public array $changes = [],
        public array $metadata = [],
    ) {}
}
