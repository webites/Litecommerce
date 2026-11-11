<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Delivery\Application\ConfigureDelivery;
use Litecommerce\Modules\Delivery\Application\RegistryDeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Delivery\Contracts\DeliverySetup;
use Litecommerce\Modules\Delivery\Contracts\ShippingMethodRegistry;
use Litecommerce\Modules\Delivery\Infrastructure\ConfiguredShippingMethodSource;
use Litecommerce\Modules\Delivery\Infrastructure\InMemoryShippingMethodRegistry;
use Litecommerce\Modules\Delivery\Infrastructure\Models\DeliveryMethod;
use Litecommerce\Modules\Delivery\Presentation\Controllers\DeliveryController;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;
use Litecommerce\Modules\Store\Contracts\SetupChecklist;

final class DeliveryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DeliveryMethodCatalog::class, RegistryDeliveryMethodCatalog::class);
        $this->app->bind(DeliverySetup::class, ConfigureDelivery::class);
        $this->app->singleton(ShippingMethodRegistry::class, function ($app): ShippingMethodRegistry {
            $registry = new InMemoryShippingMethodRegistry;
            $registry->addSource($app->make(ConfiguredShippingMethodSource::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'delivery');

        $this->app->make(SettingsTabRegistry::class)->register(
            'delivery',
            'store::tabs.delivery',
            200,
            [$this->app->make(DeliveryController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageDelivery->value),
        );

        $this->app->make(SetupChecklist::class)->register(
            'delivery',
            200,
            'delivery::panel.setup_title',
            'delivery::panel.setup_description',
            '/panel/settings#delivery',
            static fn (): bool => DeliveryMethod::query()
                ->where('active', true)
                ->where(static fn ($query) => $query->where('kind', '!=', 'pickup')->orWhereHas('pickupPoints', static fn ($points) => $points->where('active', true)))
                ->exists(),
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageDelivery->value),
        );
    }
}
