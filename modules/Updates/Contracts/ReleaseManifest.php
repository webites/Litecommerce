<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

final readonly class ReleaseManifest
{
    public const FORMAT = 1;

    /**
     * @param  list<string>  $requiredExtensions
     * @param  array<string, string>  $files  relative path => SHA-256 hex
     */
    public function __construct(
        public SemanticVersion $version,
        public string $minimumPhpVersion,
        public array $requiredExtensions,
        public ?SemanticVersion $upgradeableFrom,
        public string $builtAt,
        public array $files,
    ) {}
}
