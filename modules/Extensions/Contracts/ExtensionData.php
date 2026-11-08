<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

final readonly class ExtensionData
{
    /** @param list<string> $dependencyIds */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $type,
        public ExtensionStatus $status,
        public array $dependencyIds,
        public ?\DateTimeImmutable $installedAt,
        public ?\DateTimeImmutable $enabledAt,
        public ?string $packageSha256 = null,
    ) {}
}
