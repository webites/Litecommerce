<?php

use Litecommerce\Modules\Audit\Providers\AuditServiceProvider;
use Litecommerce\Modules\Backup\Providers\BackupServiceProvider;
use Litecommerce\Modules\Blog\Providers\BlogServiceProvider;
use Litecommerce\Modules\Cache\Providers\CacheServiceProvider;
use Litecommerce\Modules\Cart\Providers\CartServiceProvider;
use Litecommerce\Modules\Catalog\Providers\CatalogServiceProvider;
use Litecommerce\Modules\Cms\Providers\CmsServiceProvider;
use Litecommerce\Modules\Consents\Providers\ConsentsServiceProvider;
use Litecommerce\Modules\Coupons\Providers\CouponsServiceProvider;
use Litecommerce\Modules\Delivery\Providers\DeliveryServiceProvider;
use Litecommerce\Modules\DemoData\Providers\DemoDataServiceProvider;
use Litecommerce\Modules\Digital\Providers\DigitalServiceProvider;
use Litecommerce\Modules\Email\Providers\EmailServiceProvider;
use Litecommerce\Modules\Extensions\Providers\ExtensionsServiceProvider;
use Litecommerce\Modules\Identity\Providers\IdentityServiceProvider;
use Litecommerce\Modules\Installer\Providers\InstallerServiceProvider;
use Litecommerce\Modules\Maintenance\Providers\MaintenanceServiceProvider;
use Litecommerce\Modules\Media\Providers\MediaServiceProvider;
use Litecommerce\Modules\Notifications\Providers\NotificationsServiceProvider;
use Litecommerce\Modules\Orders\Providers\OrdersServiceProvider;
use Litecommerce\Modules\Payments\Providers\PaymentsServiceProvider;
use Litecommerce\Modules\Pricing\Providers\PricingServiceProvider;
use Litecommerce\Modules\Redirects\Providers\RedirectsServiceProvider;
use Litecommerce\Modules\Returns\Providers\ReturnsServiceProvider;
use Litecommerce\Modules\Scheduler\Providers\SchedulerServiceProvider;
use Litecommerce\Modules\Search\Providers\SearchServiceProvider;
use Litecommerce\Modules\Setup\Providers\SetupServiceProvider;
use Litecommerce\Modules\Store\Providers\StoreServiceProvider;
use Litecommerce\Modules\Storefront\Providers\StorefrontServiceProvider;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use Litecommerce\Modules\Updates\Providers\UpdatesServiceProvider;
use Litecommerce\Modules\Warehouse\Providers\WarehouseServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Domain module registry
    |--------------------------------------------------------------------------
    |
    | Each entry is one bounded context in modules/<Module>. Array order
    | determines provider registration order — keep it consistent with
    | inter-module dependencies. Fully manual: no auto-discovery of
    | modules/ directories at runtime.
    |
    | 'enabled' will also cover MVP-disableable modules (Coupons, Blog —
    | docs/knowledge-base/01-scope.md), not just provider presence.
    |
    */

    'modules' => [

        'audit' => [
            'enabled' => true,
            'provider' => AuditServiceProvider::class,
        ],

        'identity' => [
            'enabled' => true,
            'provider' => IdentityServiceProvider::class,
        ],

        'backup' => [
            'enabled' => true,
            'provider' => BackupServiceProvider::class,
        ],

        'maintenance' => [
            'enabled' => true,
            'provider' => MaintenanceServiceProvider::class,
        ],

        'notifications' => [
            'enabled' => true,
            'provider' => NotificationsServiceProvider::class,
        ],

        'updates' => [
            'enabled' => true,
            'provider' => UpdatesServiceProvider::class,
        ],

        'consents' => [
            'enabled' => true,
            'provider' => ConsentsServiceProvider::class,
        ],

        'cache' => [
            'enabled' => true,
            'provider' => CacheServiceProvider::class,
        ],

        'store' => [
            'enabled' => true,
            'provider' => StoreServiceProvider::class,
        ],

        'setup' => [
            'enabled' => true,
            'provider' => SetupServiceProvider::class,
        ],

        'scheduler' => [
            'enabled' => true,
            'provider' => SchedulerServiceProvider::class,
        ],

        'email' => [
            'enabled' => true,
            'provider' => EmailServiceProvider::class,
        ],

        'media' => [
            'enabled' => true,
            'provider' => MediaServiceProvider::class,
        ],

        'catalog' => [
            'enabled' => true,
            'provider' => CatalogServiceProvider::class,
        ],

        'pricing' => [
            'enabled' => true,
            'provider' => PricingServiceProvider::class,
        ],

        'warehouse' => [
            'enabled' => true,
            'provider' => WarehouseServiceProvider::class,
        ],

        // Read-optimized storefront projection composed only through public
        // Catalog/Pricing/Warehouse contracts; registered before Storefront.
        'search' => [
            'enabled' => true,
            'provider' => SearchServiceProvider::class,
        ],

        'delivery' => [
            'enabled' => true,
            'provider' => DeliveryServiceProvider::class,
        ],

        'digital' => [
            'enabled' => true,
            'provider' => DigitalServiceProvider::class,
        ],

        // "wyłączalny" per docs/knowledge-base/01-scope.md refers to the
        // merchant-facing coupon_settings.enabled toggle (Panel > Kupony),
        // not this compile-time provider registration — see the comment on
        // coupon_settings in the P1-011 migration.
        'coupons' => [
            'enabled' => true,
            'provider' => CouponsServiceProvider::class,
        ],

        'cart' => [
            'enabled' => true,
            'provider' => CartServiceProvider::class,
        ],

        'orders' => [
            'enabled' => true,
            'provider' => OrdersServiceProvider::class,
        ],

        'payments' => [
            'enabled' => true,
            'provider' => PaymentsServiceProvider::class,
        ],

        'returns' => [
            'enabled' => true,
            'provider' => ReturnsServiceProvider::class,
        ],

        // Boots after every business module so a plugin's boot() can rely
        // on their contracts (e.g. Payments\Contracts\PaymentMethodRegistry)
        // already being bound — see "Rozruch i rozszerzalność"
        // (08-extensions.md#projekt-techniczny-p1-012).
        'extensions' => [
            'enabled' => true,
            'provider' => ExtensionsServiceProvider::class,
        ],

        // After 'extensions' (activation checks a theme's required plugins
        // via Extensions\Contracts\ExtensionCatalog) and before any module
        // that renders storefront pages — see "Moduł Themes"
        // (09-presentation.md#projekt-techniczny-p1-013).
        'themes' => [
            'enabled' => true,
            'provider' => ThemesServiceProvider::class,
        ],

        // Public storefront routes/controllers — after 'themes' so the
        // active theme's `theme::` view namespace already exists once a
        // real request reaches a Storefront controller.
        'storefront' => [
            'enabled' => true,
            'provider' => StorefrontServiceProvider::class,
        ],

        // After 'storefront' — Cms\Infrastructure\ThemeBlockRenderer consumes
        // Storefront\Contracts\ProductListingItemAssembler for the product-grid
        // block (see 09-presentation.md#cms-i-blog).
        'cms' => [
            'enabled' => true,
            'provider' => CmsServiceProvider::class,
        ],

        // Always registered so disabling the merchant-facing store setting
        // preserves content and handlers needed to manage existing data.
        'blog' => [
            'enabled' => true,
            'provider' => BlogServiceProvider::class,
        ],

        // Always on, not merchant-toggleable — a redirect a merchant created
        // must keep working even if some future release made this optional.
        'redirects' => [
            'enabled' => true,
            'provider' => RedirectsServiceProvider::class,
        ],

        // Registered after all content-owning modules. Their providers tag
        // contributors; this coordinator only orchestrates public contracts.
        'demo-data' => [
            'enabled' => true,
            'provider' => DemoDataServiceProvider::class,
        ],

        'installer' => [
            'enabled' => true,
            'provider' => InstallerServiceProvider::class,
        ],

    ],

];
