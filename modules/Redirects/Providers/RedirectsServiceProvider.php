<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Redirects\Contracts\RedirectResolver;
use Litecommerce\Modules\Redirects\Infrastructure\Repositories\EloquentRedirectResolver;
use Litecommerce\Modules\Redirects\Presentation\Controllers\RedirectController;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;

final class RedirectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RedirectResolver::class, EloquentRedirectResolver::class);
    }

    public function boot(): void
    {
        $this->app->make(SettingsTabRegistry::class)->register(
            'redirects',
            'store::tabs.redirects',
            600,
            [$this->app->make(RedirectController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageStoreSettings->value),
        );
    }
}
