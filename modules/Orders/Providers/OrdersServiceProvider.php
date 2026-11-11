<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Providers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Orders\Application\EloquentCustomerOrderCatalog;
use Litecommerce\Modules\Orders\Application\EloquentGuestOrderAccess;
use Litecommerce\Modules\Orders\Application\EloquentOrderConfirmationCatalog;
use Litecommerce\Modules\Orders\Application\EloquentOrderPaymentCatalog;
use Litecommerce\Modules\Orders\Application\EloquentOrderReturnCatalog;
use Litecommerce\Modules\Orders\Application\GrantDigitalAccessAfterLatePayment;
use Litecommerce\Modules\Orders\Application\ManageOrderFulfillment;
use Litecommerce\Modules\Orders\Application\ManageOrderPayment;
use Litecommerce\Modules\Orders\Application\PlaceOrder;
use Litecommerce\Modules\Orders\Console\AnonymizeExpiredOrdersCommand;
use Litecommerce\Modules\Orders\Console\PruneGuestOrderTokensCommand;
use Litecommerce\Modules\Orders\Contracts\CustomerOrderCatalog;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Orders\Contracts\OrderConfirmationCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderDigitalAccessGranting;
use Litecommerce\Modules\Orders\Contracts\OrderFulfillmentManager;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Orders\Contracts\OrderReturnCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderStatusDefinition;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;
use Litecommerce\Modules\Orders\Infrastructure\InMemoryOrderStatusRegistry;
use Litecommerce\Modules\Orders\Presentation\Controllers\OrderSettingsController;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;

final class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/orders.php', 'orders');
        $this->app->bind(OrderPlacer::class, PlaceOrder::class);
        $this->app->bind(OrderPaymentCatalog::class, EloquentOrderPaymentCatalog::class);
        $this->app->bind(OrderPaymentManager::class, ManageOrderPayment::class);
        $this->app->bind(OrderFulfillmentManager::class, ManageOrderFulfillment::class);
        $this->app->bind(OrderDigitalAccessGranting::class, GrantDigitalAccessAfterLatePayment::class);
        $this->app->bind(OrderReturnCatalog::class, EloquentOrderReturnCatalog::class);
        $this->app->bind(OrderConfirmationCatalog::class, EloquentOrderConfirmationCatalog::class);
        $this->app->bind(CustomerOrderCatalog::class, EloquentCustomerOrderCatalog::class);
        $this->app->bind(GuestOrderAccess::class, EloquentGuestOrderAccess::class);
        $this->app->singleton(OrderStatusRegistry::class, InMemoryOrderStatusRegistry::class);
    }

    public function boot(OrderStatusRegistry $statuses): void
    {
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('orders.');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'orders');

        $this->app->make(ScheduledTaskRegistry::class)->register(
            'core.orders.expire_uncollected',
            300,
            label: 'orders::tasks.expire_uncollected',
            handler: fn () => $this->app->make(OrderFulfillmentManager::class)->expireDue(
                new \DateTimeImmutable,
                (int) config('orders.pickup_expiry_batch', 50),
            ),
        );

        $this->app->make(ScheduledTaskRegistry::class)->register(
            'core.orders.remind_pickup',
            300,
            label: 'orders::tasks.remind_pickup',
            handler: fn () => $this->app->make(OrderFulfillmentManager::class)->remindDue(
                new \DateTimeImmutable,
                (int) config('orders.pickup_expiry_batch', 50),
            ),
        );

        $this->commands([PruneGuestOrderTokensCommand::class, AnonymizeExpiredOrdersCommand::class]);
        $this->app->make(ScheduledTaskRegistry::class)->register('core.orders.anonymize_expired', 86400, label: 'orders::tasks.anonymize_expired', handler: static function (): void {
            Artisan::call('orders:anonymize-expired');
        });
        $this->app->make(ScheduledTaskRegistry::class)->register('core.orders.prune_guest_tokens', 86400, label: 'orders::tasks.prune_guest_tokens', handler: static function (): void {
            Artisan::call('orders:prune-guest-tokens');
        });

        $this->app->make(SettingsTabRegistry::class)->register(
            'orders',
            'orders::panel.settings.tab',
            250,
            [$this->app->make(OrderSettingsController::class), 'settingsTab'],
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageStoreSettings->value),
        );

        $statuses->register(new OrderStatusDefinition('pending_payment', 'orders::statuses.pending_payment', true));
        $statuses->register(new OrderStatusDefinition('processing', 'orders::statuses.processing', true));
        $statuses->register(new OrderStatusDefinition('cancelled', 'orders::statuses.cancelled', true));
        $statuses->register(new OrderStatusDefinition('payment_review', 'orders::statuses.payment_review', false));
    }
}
