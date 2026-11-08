<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Returns\Application\EloquentReturnCatalog;
use Litecommerce\Modules\Returns\Console\AnonymizeExpiredReturnsCommand;
use Litecommerce\Modules\Returns\Contracts\ReturnCatalog;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class ReturnsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReturnCatalog::class, EloquentReturnCatalog::class);
    }

    public function boot(): void
    {
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('returns.');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'returns');
        $this->commands([AnonymizeExpiredReturnsCommand::class]);

        $this->app->make(ScheduledTaskRegistry::class)->register('core.returns.anonymize_expired', 86400, static function (): void {
            Artisan::call('returns:anonymize-expired');
        });
    }
}
