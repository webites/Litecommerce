<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Updates\Contracts\CompatibilityReport;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;

final class CompatibilityChecker
{
    /** @param list<string> $writablePaths application-relative paths an update replaces */
    public function __construct(
        private readonly EnvironmentProbe $environment,
        private readonly array $writablePaths,
        private readonly int $minimumFreeBytes,
    ) {}

    public function check(ReleaseManifest $manifest, SemanticVersion $current): CompatibilityReport
    {
        $problems = [];

        if ($manifest->version->compareTo($current) <= 0) {
            $problems[] = ['code' => 'not_newer', 'params' => ['current' => $current->toString(), 'target' => $manifest->version->toString()]];
        }

        if ($manifest->upgradeableFrom !== null && $current->compareTo($manifest->upgradeableFrom) < 0) {
            $problems[] = ['code' => 'upgrade_from_required', 'params' => ['version' => $manifest->upgradeableFrom->toString()]];
        }

        if (version_compare($this->environment->phpVersion(), $manifest->minimumPhpVersion, '<')) {
            $problems[] = ['code' => 'php_too_old', 'params' => ['required' => $manifest->minimumPhpVersion, 'current' => $this->environment->phpVersion()]];
        }

        foreach ($manifest->requiredExtensions as $extension) {
            if (! $this->environment->hasExtension($extension)) {
                $problems[] = ['code' => 'extension_missing', 'params' => ['extension' => $extension]];
            }
        }

        foreach ($this->writablePaths as $path) {
            if (! $this->environment->isWritable($path)) {
                $problems[] = ['code' => 'not_writable', 'params' => ['path' => $path]];
            }
        }

        $free = $this->environment->freeBytes();
        if ($free !== null && $free < $this->minimumFreeBytes) {
            $problems[] = ['code' => 'low_disk_space', 'params' => ['required_mb' => (string) intdiv($this->minimumFreeBytes, 1048576)]];
        }

        return new CompatibilityReport($problems);
    }
}
