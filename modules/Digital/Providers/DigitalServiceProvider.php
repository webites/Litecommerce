<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Digital\Application\GrantDigitalAccess;
use Litecommerce\Modules\Digital\Application\ManageDigitalAccess;
use Litecommerce\Modules\Digital\Application\ManageDigitalFiles;
use Litecommerce\Modules\Digital\Console\AnonymizeExpiredGrantsCommand;
use Litecommerce\Modules\Digital\Console\ScrubDownloadAttemptsCommand;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessManager;
use Litecommerce\Modules\Digital\Contracts\DigitalFileCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalFileManager;
use Litecommerce\Modules\Digital\Infrastructure\Repositories\EloquentDigitalAccessCatalog;
use Litecommerce\Modules\Digital\Infrastructure\Repositories\EloquentDigitalFileCatalog;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class DigitalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/digital.php', 'digital');

        $this->app->bind(DigitalFileCatalog::class, EloquentDigitalFileCatalog::class);
        $this->app->bind(DigitalFileManager::class, ManageDigitalFiles::class);
        $this->app->bind(DigitalAccessGrantor::class, GrantDigitalAccess::class);
        $this->app->bind(DigitalAccessCatalog::class, EloquentDigitalAccessCatalog::class);
        $this->app->bind(DigitalAccessManager::class, ManageDigitalAccess::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'digital');
        $this->commands([ScrubDownloadAttemptsCommand::class, AnonymizeExpiredGrantsCommand::class]);

        $this->app->make(ScheduledTaskRegistry::class)->register('core.digital.scrub_download_attempts', 86400, static function (): void {
            Artisan::call('digital:scrub-download-attempts');
        });
        $this->app->make(ScheduledTaskRegistry::class)->register('core.digital.anonymize_expired_grants', 86400, static function (): void {
            Artisan::call('digital:anonymize-expired-grants');
        });
    }
}
