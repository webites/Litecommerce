<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

final readonly class UpdateStatus
{
    public function __construct(
        public SemanticVersion $currentVersion,
        public ?ReleaseInfo $availableRelease,
        public CompatibilityReport $compatibility,
        public ?string $checkedAt,
        public ?string $errorCode,
        public bool $automaticChecks,
    ) {}
}
