<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Notifications\Application\PanelMessageReadStore;
use Litecommerce\Modules\Notifications\Application\StaffPanelInbox;
use Litecommerce\Modules\Notifications\Contracts\PanelInbox;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageRegistry;
use Litecommerce\Modules\Notifications\Infrastructure\DatabasePanelMessageReadStore;
use Litecommerce\Modules\Notifications\Infrastructure\InMemoryPanelMessageRegistry;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PanelMessageRegistry::class, InMemoryPanelMessageRegistry::class);
        $this->app->bind(PanelMessageReadStore::class, DatabasePanelMessageReadStore::class);
        $this->app->bind(PanelInbox::class, StaffPanelInbox::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'notifications');
    }
}
