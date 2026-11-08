<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Application;

use Illuminate\Support\Facades\Artisan;
use Litecommerce\Modules\Extensions\Contracts\ExtensionInstallationException;
use Litecommerce\Modules\Extensions\Contracts\PluginInstallHook;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\PluginProviderResolver;

// Runs the plugin's own migrations (once) and its optional install() hook; idempotent, a no-op if already installed.
final class InstallExtension
{
    public function __construct(private readonly PluginProviderResolver $providers) {}

    public function execute(string $extensionId): void
    {
        $extension = Extension::query()->findOrFail($extensionId);

        if ($extension->installed_at !== null) {
            return;
        }

        $migrationsPath = $this->providers->packagePath($extensionId).'/database/migrations';

        if (is_dir($migrationsPath)) {
            try {
                Artisan::call('migrate', ['--path' => $migrationsPath, '--realpath' => true, '--force' => true]);
            } catch (\Throwable $exception) {
                throw new ExtensionInstallationException('migration_failed', 'Extension migrations failed: '.$exception->getMessage(), ['message' => $exception->getMessage()]);
            }
        }

        $provider = $this->providers->resolve($extension);
        if ($provider instanceof PluginInstallHook) {
            $provider->install();
        }

        $extension->update(['installed_at' => now()]);
    }
}
