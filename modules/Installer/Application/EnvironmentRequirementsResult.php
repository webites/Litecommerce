<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Application;

final readonly class EnvironmentRequirementsResult
{
    /**
     * @param  list<string>  $missingExtensions
     * @param  list<string>  $unwritablePaths
     */
    public function __construct(
        public bool $phpVersionSatisfied,
        public array $missingExtensions,
        public array $unwritablePaths,
    ) {}

    public function satisfied(): bool
    {
        return $this->phpVersionSatisfied && $this->missingExtensions === [] && $this->unwritablePaths === [];
    }
}
