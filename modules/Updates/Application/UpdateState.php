<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

/** @phpstan-type Problem array{code: string, params: array<string, string>} */
final readonly class UpdateState
{
    /**
     * @param  array<string, mixed>|null  $release  serialized ReleaseInfo
     * @param  list<array{code: string, params: array<string, string>}>  $problems
     */
    public function __construct(
        public bool $automaticChecks = true,
        public ?string $checkedAt = null,
        public ?string $errorCode = null,
        public ?array $release = null,
        public array $problems = [],
    ) {}
}
