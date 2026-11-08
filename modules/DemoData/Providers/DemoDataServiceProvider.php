<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\DemoData\Application\ManageDemoData;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\DemoData\Infrastructure\EloquentDemoDataResourceRegistry;
use Litecommerce\Modules\DemoData\Infrastructure\TranslatorDemoDataContent;
use Litecommerce\Modules\DemoData\Presentation\Console\ListDemoDataCommand;
use Litecommerce\Modules\DemoData\Presentation\Console\RemoveDemoDataCommand;
use Litecommerce\Modules\DemoData\Presentation\Console\SeedDemoDataCommand;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class DemoDataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DemoDataResourceRegistry::class, EloquentDemoDataResourceRegistry::class);
        $this->app->bind(DemoDataContent::class, TranslatorDemoDataContent::class);
        $this->app->bind(DemoDataManager::class, fn ($app): ManageDemoData => new ManageDemoData(
            $app->make(LanguageRepository::class),
            $app->make(CurrencyRepository::class),
            $app->make(DemoDataResourceRegistry::class),
            $app->tagged(DemoDataContributor::class),
        ));
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'demo-data');
        $this->commands([SeedDemoDataCommand::class, RemoveDemoDataCommand::class, ListDemoDataCommand::class]);
    }
}
