<?php

declare(strict_types=1);

namespace Tests\Feature;

use Litecommerce\Modules\Delivery\Contracts\DeliveryMethodCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessGrantor;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessManager;
use Litecommerce\Modules\Digital\Contracts\DigitalFileCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalFileManager;
use Litecommerce\Modules\Installer\Application\DatabaseConnectionInstaller;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentCatalog;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptCatalog;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStarter;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Payments\Contracts\PaymentExpiryReconciler;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Returns\Contracts\ReturnCatalog;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Tests\TestCase;

final class ModuleRegistrySmokeTest extends TestCase
{
    public function test_enabled_module_providers_are_registered_and_resolvable(): void
    {
        $this->assertInstanceOf(CurrencyRepository::class, $this->app->make(CurrencyRepository::class));
        $this->assertInstanceOf(LanguageRepository::class, $this->app->make(LanguageRepository::class));
        $this->assertInstanceOf(WarehouseProvisioner::class, $this->app->make(WarehouseProvisioner::class));
        $this->assertInstanceOf(StockLevelCatalog::class, $this->app->make(StockLevelCatalog::class));
        $this->assertInstanceOf(StockAdjuster::class, $this->app->make(StockAdjuster::class));
        $this->assertInstanceOf(StockReservationManager::class, $this->app->make(StockReservationManager::class));
        $this->assertInstanceOf(DeliveryMethodCatalog::class, $this->app->make(DeliveryMethodCatalog::class));
        $this->assertInstanceOf(DigitalFileCatalog::class, $this->app->make(DigitalFileCatalog::class));
        $this->assertInstanceOf(DigitalFileManager::class, $this->app->make(DigitalFileManager::class));
        $this->assertInstanceOf(DigitalAccessGrantor::class, $this->app->make(DigitalAccessGrantor::class));
        $this->assertInstanceOf(DigitalAccessCatalog::class, $this->app->make(DigitalAccessCatalog::class));
        $this->assertInstanceOf(DigitalAccessManager::class, $this->app->make(DigitalAccessManager::class));
        $this->assertInstanceOf(OrderPlacer::class, $this->app->make(OrderPlacer::class));
        $this->assertInstanceOf(OrderPaymentCatalog::class, $this->app->make(OrderPaymentCatalog::class));
        $this->assertInstanceOf(OrderPaymentManager::class, $this->app->make(OrderPaymentManager::class));
        $this->assertInstanceOf(OrderStatusRegistry::class, $this->app->make(OrderStatusRegistry::class));
        $this->assertInstanceOf(PaymentMethodRegistry::class, $this->app->make(PaymentMethodRegistry::class));
        $this->assertInstanceOf(PaymentAttemptCatalog::class, $this->app->make(PaymentAttemptCatalog::class));
        $this->assertInstanceOf(PaymentAttemptStarter::class, $this->app->make(PaymentAttemptStarter::class));
        $this->assertInstanceOf(PaymentConfirmation::class, $this->app->make(PaymentConfirmation::class));
        $this->assertInstanceOf(PaymentExpiryReconciler::class, $this->app->make(PaymentExpiryReconciler::class));
        $this->assertInstanceOf(DatabaseConnectionInstaller::class, $this->app->make(DatabaseConnectionInstaller::class));
        $this->assertInstanceOf(ReturnCatalog::class, $this->app->make(ReturnCatalog::class));
        $this->assertInstanceOf(ScheduledTaskRegistry::class, $this->app->make(ScheduledTaskRegistry::class));
    }
}
