<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

final readonly class ThemeData
{
    /** @param list<string> $requiredPluginIds */
    public function __construct(
        public string $id,
        public string $name,
        public string $version,
        public string $engine,
        public ThemeStatus $status,
        public array $requiredPluginIds,
        public ?\DateTimeImmutable $installedAt,
        public ?\DateTimeImmutable $activatedAt,
        public ?string $packageSha256 = null,
    ) {}
}
