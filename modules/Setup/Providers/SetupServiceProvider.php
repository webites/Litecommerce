<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Setup\Providers;

use Illuminate\Support\ServiceProvider;

final class SetupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'setup');
    }
}
