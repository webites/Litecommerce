<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Contracts;

interface EnvironmentRequirements
{
    public function minimumPhpVersion(): string;

    /** @return list<string> */
    public function requiredExtensions(): array;
}
