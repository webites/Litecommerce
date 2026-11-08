<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Infrastructure\FileMaintenanceMode;

final class MaintenanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/maintenance.php', 'maintenance');

        $this->app->bind(MaintenanceMode::class, FileMaintenanceMode::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'maintenance');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'maintenance');
    }
}
