<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Manual module provider registry from config/modules.php.
 *
 * No auto-discovery/scanning of modules/ at runtime by design: filesystem
 * scans on every request are costly on shared hosting, and an explicit
 * list caches cleanly via `php artisan config:cache`.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach ($this->enabledModuleProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    private function enabledModuleProviders(): array
    {
        $modules = config('modules.modules', []);
        $providers = [];

        foreach ($modules as $key => $module) {
            if (! ($module['enabled'] ?? false)) {
                continue;
            }

            if (empty($module['provider'])) {
                throw new RuntimeException("Moduł [{$key}] jest włączony, ale nie ma skonfigurowanego providera w config/modules.php.");
            }

            $providers[] = $module['provider'];
        }

        return $providers;
    }
}
