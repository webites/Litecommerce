<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Contracts;

final readonly class CronSetupResult
{
    public const INSTALLED = 'installed';

    public const ALREADY_INSTALLED = 'already_installed';

    public const UNSUPPORTED = 'unsupported';

    public const FAILED = 'failed';

    /** @param string|null $reason Machine-readable cause for UNSUPPORTED and FAILED. */
    public function __construct(
        public string $status,
        public ?string $reason = null,
        public ?string $line = null,
    ) {}
}
