<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Application;

final readonly class DatabaseConnectionResult
{
    public function __construct(
        public bool $successful,
        public ?string $errorMessage = null,
    ) {}
}
