<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Console\PruneAuditEntriesCommand;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Audit\Infrastructure\EloquentAuditLogger;
use Litecommerce\Modules\Audit\Infrastructure\InMemoryAuditRetentionRegistry;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/audit.php', 'audit');

        $this->app->bind(AuditLogger::class, EloquentAuditLogger::class);
        $this->app->singleton(AuditRetentionRegistry::class, InMemoryAuditRetentionRegistry::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'audit');
        $this->commands([PruneAuditEntriesCommand::class]);

        $this->app->make(ScheduledTaskRegistry::class)->register('core.audit.prune', 86400, static function (): void {
            Artisan::call('audit:prune');
        });
    }
}
