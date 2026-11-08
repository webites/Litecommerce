<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Installer\Application\DatabaseConnectionInstaller;
use Litecommerce\Modules\Installer\Infrastructure\EnvFileWriter;
use Litecommerce\Modules\Installer\Infrastructure\PdoDatabaseConnectionInstaller;

final class InstallerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseConnectionInstaller::class, PdoDatabaseConnectionInstaller::class);

        $this->app->bind(EnvFileWriter::class, fn () => new EnvFileWriter(base_path('.env')));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'installer');
    }
}
