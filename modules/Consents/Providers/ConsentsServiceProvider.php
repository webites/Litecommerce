<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Consents\Application\ManageMarketingConsents;
use Litecommerce\Modules\Consents\Console\PruneWithdrawnConsentsCommand;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class ConsentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/consents.php', 'consents');

        $this->app->bind(MarketingConsentRegistry::class, ManageMarketingConsents::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'consents');
        $this->commands([PruneWithdrawnConsentsCommand::class]);

        $this->app->make(ScheduledTaskRegistry::class)->register('core.consents.prune_withdrawn', 86400, label: 'consents::tasks.prune_withdrawn', handler: static function (): void {
            Artisan::call('consents:prune-withdrawn');
        });
    }
}
