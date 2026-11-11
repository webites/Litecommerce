<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Providers;

use App\Support\Secrets\SecretReencryptor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Email\Contracts\EmailTransportObligationChecker;
use Litecommerce\Modules\Extensions\Application\EnableExtension;
use Litecommerce\Modules\Extensions\Application\FinishExtensionWindDown;
use Litecommerce\Modules\Extensions\Application\UninstallExtension;
use Litecommerce\Modules\Extensions\Application\UploadExtensionPackage;
use Litecommerce\Modules\Extensions\Contracts\ExtensionCatalog;
use Litecommerce\Modules\Extensions\Contracts\ExtensionSettingsStore;
use Litecommerce\Modules\Extensions\Contracts\PanelSlotRegistry;
use Litecommerce\Modules\Extensions\Contracts\PluginServiceProvider;
use Litecommerce\Modules\Extensions\Contracts\PluginWindDownHook;
use Litecommerce\Modules\Extensions\Domain\ManifestParser;
use Litecommerce\Modules\Extensions\Infrastructure\EloquentExtensionCatalog;
use Litecommerce\Modules\Extensions\Infrastructure\EloquentExtensionSettingsStore;
use Litecommerce\Modules\Extensions\Infrastructure\ExtensionSecretReencryptor;
use Litecommerce\Modules\Extensions\Infrastructure\InMemoryPanelSlotRegistry;
use Litecommerce\Modules\Extensions\Infrastructure\Models\Extension;
use Litecommerce\Modules\Extensions\Infrastructure\PluginAutoloader;
use Litecommerce\Modules\Extensions\Infrastructure\PluginProviderResolver;
use Litecommerce\Modules\Identity\Contracts\PermissionDefinition;
use Litecommerce\Modules\Identity\Contracts\PermissionRegistry;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExtensionCatalog::class, EloquentExtensionCatalog::class);
        $this->app->bind(ExtensionSettingsStore::class, EloquentExtensionSettingsStore::class);
        $this->app->singleton(PanelSlotRegistry::class, InMemoryPanelSlotRegistry::class);
        $this->app->singleton(PluginAutoloader::class);
        $this->app->tag(ExtensionSecretReencryptor::class, SecretReencryptor::class);

        $this->app->singleton(PluginProviderResolver::class, fn (Application $app): PluginProviderResolver => new PluginProviderResolver(
            $app->make(PluginAutoloader::class),
            (string) config('extensions.disk'),
            (string) config('extensions.path'),
        ));

        $this->app->bind(UploadExtensionPackage::class, fn (Application $app): UploadExtensionPackage => new UploadExtensionPackage(
            $app->make(ManifestParser::class),
            (int) config('extensions.max_package_size_mb') * 1024 * 1024,
            (int) config('extensions.max_file_count'),
            (string) config('extensions.disk'),
            (string) config('extensions.path'),
        ));

        $this->app->bind(EnableExtension::class, fn (): EnableExtension => new EnableExtension(
            (string) config('extensions.core_version'),
            PHP_VERSION,
        ));

        $this->app->bind(UninstallExtension::class, fn (Application $app): UninstallExtension => new UninstallExtension(
            $app->make(PluginProviderResolver::class),
            $app->make(EmailTransportObligationChecker::class),
            (string) config('extensions.disk'),
            (string) config('extensions.path'),
        ));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'extensions');

        $tasks = $this->app->make(ScheduledTaskRegistry::class);
        if (! collect($tasks->all())->contains(static fn ($task): bool => $task->key === 'core.extensions.finish_wind_down')) {
            $tasks->register('core.extensions.finish_wind_down', 300, label: 'extensions::tasks.finish_wind_down', handler: fn () => $this->app->make(FinishExtensionWindDown::class)->run());
        }

        // Not-yet-installed shop (no schema yet) or a database briefly unreachable: extensions stay dormant.
        try {
            if (! Schema::hasTable('extensions')) {
                return;
            }
            $enabled = Extension::query()->whereIn('status', ['enabled', 'winding_down'])->get();
        } catch (\Throwable) {
            return;
        }

        $resolver = $this->app->make(PluginProviderResolver::class);
        $permissions = $this->app->make(PermissionRegistry::class);

        foreach ($enabled as $extension) {
            try {
                $provider = $resolver->resolve($extension);

                foreach ($extension->manifest['permissions'] ?? [] as $permission) {
                    // Administrator gets every plugin-declared permission by
                    // default, same as every core permission (see
                    // IdentityServiceProvider) — a specific staff account's
                    // own permission_overrides can still explicitly deny it.
                    $permissions->register(new PermissionDefinition(
                        (string) $permission['key'],
                        (string) ($permission['label'] ?? $permission['key']),
                        $extension->name,
                        [SystemStaffRole::Administrator->value],
                    ));
                }

                if ($extension->status === 'winding_down') {
                    if ($provider instanceof PluginWindDownHook) {
                        $provider->windDown($this->app);
                    }
                } elseif ($provider instanceof PluginServiceProvider) {
                    $provider->register($this->app);
                    $provider->boot($this->app);
                }
            } catch (\Throwable $exception) {
                // One broken plugin must never take the whole panel/storefront down — log and continue.
                Log::error("Failed to boot extension [{$extension->id}].", ['exception' => $exception]);
            }
        }
    }
}
