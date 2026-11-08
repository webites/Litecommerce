<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Application;

use App\Support\Versioning\VersionConstraint;
use Litecommerce\Modules\Extensions\Contracts\ExtensionStateException;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;

/**
 * Compatibility and dependency checks are re-evaluated here, against
 * *current* state, not just at upload time — the core version or an
 * already-installed dependency's version can change independently in the
 * meantime ("Walidacja zależności w obu kierunkach przed zmianą",
 * 10-operations.md, written for updates but the same reasoning applies).
 */
final class EnableExtension
{
    public function __construct(
        private readonly string $coreVersion,
        private readonly string $phpVersion,
    ) {}

    public function execute(string $extensionId): void
    {
        $extension = Extension::query()->findOrFail($extensionId);

        if ($extension->installed_at === null) {
            throw new ExtensionStateException('not_installed', 'Rozszerzenie musi zostać najpierw zainstalowane.');
        }

        if ($extension->status === 'enabled') {
            return;
        }

        $manifest = $extension->manifest;
        $coreConstraint = (string) ($manifest['core'] ?? '*');
        $phpConstraint = (string) ($manifest['php'] ?? '*');

        if (! VersionConstraint::satisfies($this->coreVersion, $coreConstraint)) {
            throw new ExtensionStateException('core_incompatible', "Rozszerzenie wymaga rdzenia w wersji \"{$coreConstraint}\", zainstalowany rdzeń to {$this->coreVersion}.");
        }
        if (! VersionConstraint::satisfies($this->phpVersion, $phpConstraint)) {
            throw new ExtensionStateException('php_incompatible', "Rozszerzenie wymaga PHP w wersji \"{$phpConstraint}\", zainstalowane PHP to {$this->phpVersion}.");
        }

        foreach ($manifest['dependencies'] ?? [] as $dependency) {
            $dependencyId = (string) $dependency['id'];
            $dependencyConstraint = (string) ($dependency['version'] ?? '*');
            $dependencyExtension = Extension::query()->find($dependencyId);

            if ($dependencyExtension === null || $dependencyExtension->status !== 'enabled') {
                throw new ExtensionStateException('dependency_unmet', "Zależność \"{$dependencyId}\" musi być zainstalowana i włączona.");
            }
            if (! VersionConstraint::satisfies($dependencyExtension->version, $dependencyConstraint)) {
                throw new ExtensionStateException('dependency_version_mismatch', "Zależność \"{$dependencyId}\" w wersji {$dependencyExtension->version} nie spełnia wymogu \"{$dependencyConstraint}\".");
            }
        }

        $extension->update(['status' => 'enabled', 'enabled_at' => now()]);
    }
}
