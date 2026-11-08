<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Cart\Application\CartResolver;
use Litecommerce\Modules\Cart\Console\PruneInactiveCartsCommand;
use Litecommerce\Modules\Cart\Contracts\CartMerger;
use Litecommerce\Modules\Cart\Contracts\CartOwnershipResolver;
use Litecommerce\Modules\Cart\Contracts\CheckoutDraftCatalog;
use Litecommerce\Modules\Cart\Infrastructure\EloquentCartMerger;
use Litecommerce\Modules\Cart\Infrastructure\EloquentCheckoutDraftCatalog;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class CartServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/cart.php', 'cart');

        $this->app->bind(CartMerger::class, EloquentCartMerger::class);
        $this->app->bind(CheckoutDraftCatalog::class, EloquentCheckoutDraftCatalog::class);
        $this->app->bind(CartOwnershipResolver::class, CartResolver::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'cart');
        $this->commands([PruneInactiveCartsCommand::class]);

        $this->app->make(ScheduledTaskRegistry::class)->register('core.cart.prune_inactive', 86400, static function (): void {
            Artisan::call('cart:prune-inactive');
        });
    }
}
