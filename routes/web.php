<?php

use App\Http\Controllers\Panel\PanelHomeController;
use App\Http\Controllers\Panel\PanelLocaleController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Litecommerce\Modules\Blog\Presentation\Controllers\Panel\BlogCategoryController;
use Litecommerce\Modules\Blog\Presentation\Controllers\Panel\BlogPostController;
use Litecommerce\Modules\Cache\Presentation\Controllers\ClearApplicationCacheController;
use Litecommerce\Modules\Cart\Presentation\Controllers\Panel\CartOverviewController;
use Litecommerce\Modules\Catalog\Presentation\Controllers\CatalogReferenceController;
use Litecommerce\Modules\Catalog\Presentation\Controllers\CatalogSeoSettingsController;
use Litecommerce\Modules\Catalog\Presentation\Controllers\ProductController;
use Litecommerce\Modules\Cms\Presentation\Controllers\ArchetypeTemplateController;
use Litecommerce\Modules\Cms\Presentation\Controllers\MenuController;
use Litecommerce\Modules\Cms\Presentation\Controllers\PageController;
use Litecommerce\Modules\Coupons\Presentation\Controllers\Panel\CouponController;
use Litecommerce\Modules\Delivery\Presentation\Controllers\DeliveryController;
use Litecommerce\Modules\DemoData\Presentation\Controllers\Panel\DemoDataController;
use Litecommerce\Modules\Email\Presentation\Controllers\EmailSettingsController;
use Litecommerce\Modules\Extensions\Presentation\Controllers\Panel\ExtensionController;
use Litecommerce\Modules\Extensions\Presentation\Controllers\Panel\PanelSlotController;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Presentation\Controllers\StaffAccountController;
use Litecommerce\Modules\Identity\Presentation\Controllers\StaffTwoFactorSettingsController;
use Litecommerce\Modules\Installer\Presentation\Controllers\AdministratorAccountController;
use Litecommerce\Modules\Installer\Presentation\Controllers\DatabaseConfigurationController;
use Litecommerce\Modules\Installer\Presentation\Controllers\EnvironmentRequirementsController;
use Litecommerce\Modules\Installer\Presentation\Controllers\FinalizeInstallationController;
use Litecommerce\Modules\Installer\Presentation\Controllers\InstallerLocaleController;
use Litecommerce\Modules\Installer\Presentation\Middleware\NegotiateInstallerLocale;
use Litecommerce\Modules\Maintenance\Presentation\Controllers\MaintenanceController;
use Litecommerce\Modules\Media\Presentation\Controllers\MediaPickerController;
use Litecommerce\Modules\Orders\Presentation\Controllers\OrderController;
use Litecommerce\Modules\Orders\Presentation\Controllers\OrderDigitalGrantController;
use Litecommerce\Modules\Orders\Presentation\Controllers\OrderFulfillmentController;
use Litecommerce\Modules\Payments\Presentation\Controllers\PaymentSettingsController;
use Litecommerce\Modules\Pricing\Presentation\Controllers\PricingController;
use Litecommerce\Modules\Redirects\Presentation\Controllers\RedirectController;
use Litecommerce\Modules\Returns\Presentation\Controllers\Panel\ReturnController;
use Litecommerce\Modules\Scheduler\Presentation\Controllers\SchedulerSettingsController;
use Litecommerce\Modules\Setup\Presentation\Controllers\SetupWizardController;
use Litecommerce\Modules\Store\Presentation\Controllers\CurrencyController;
use Litecommerce\Modules\Store\Presentation\Controllers\LanguageController;
use Litecommerce\Modules\Store\Presentation\Controllers\RenderedSettingsTabController;
use Litecommerce\Modules\Store\Presentation\Controllers\StoreSettingsController;
use Litecommerce\Modules\Themes\Presentation\Controllers\Panel\ThemeController;
use Litecommerce\Modules\Updates\Presentation\Controllers\UpdatesController;
use Litecommerce\Modules\Warehouse\Presentation\Controllers\WarehouseController;

Route::prefix('install')->name('install.')->middleware(['no-store', NegotiateInstallerLocale::class])->group(function () {
    Route::post('/locale', [InstallerLocaleController::class, 'update'])->name('locale');
    Route::get('/', [EnvironmentRequirementsController::class, 'show'])->name('environment');
    Route::get('/database', [DatabaseConfigurationController::class, 'show'])->name('database.show');
    Route::post('/database', [DatabaseConfigurationController::class, 'store'])->name('database.store');
    Route::get('/administrator', [AdministratorAccountController::class, 'show'])->name('administrator.show');
    Route::post('/administrator', [AdministratorAccountController::class, 'store'])->name('administrator.store');
    Route::get('/finalize', [FinalizeInstallationController::class, 'show'])->name('finalize.show');
    Route::post('/finalize', [FinalizeInstallationController::class, 'store'])->name('finalize.store');
});

require __DIR__.'/auth.php';
require __DIR__.'/storefront.php';

Route::middleware(['auth', 'no-store', 'staff.permission:'.CorePermission::AccessPanel->value])->group(function () {
    // Route name must be literally "dashboard" — Laravel's default
    // RedirectIfAuthenticated::defaultRedirectUri() looks for it, and
    // AuthenticatedSessionController redirects here after login.
    Route::get('/panel', [PanelHomeController::class, 'show'])->name('dashboard');
    Route::get('/panel/wizard', [SetupWizardController::class, 'show'])->name('panel.wizard.show');
    Route::post('/panel/wizard', [SetupWizardController::class, 'store'])->name('panel.wizard.store');
    Route::post('/panel/wizard/skip', [SetupWizardController::class, 'skip'])->name('panel.wizard.skip');
    Route::post('/panel/wizard/email/test', [SetupWizardController::class, 'testEmail'])->middleware('throttle:6,1')->name('panel.wizard.email.test');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::post('/panel/locale', [PanelLocaleController::class, 'update'])->name('panel.locale.update');

    // Available to any staff with panel access, not just extension
    // managers — a dashboard widget slot is meant to be seen broadly.
    Route::get('/panel/extensions/slots/{slot}', [PanelSlotController::class, 'show'])->name('panel.extensions.slots.show');

    Route::prefix('/panel/staff')
        ->name('panel.staff.')
        ->middleware('staff.permission:'.CorePermission::ManageStaff->value)
        ->group(function (): void {
            Route::get('/', [StaffAccountController::class, 'index'])->name('index');
            Route::post('/', [StaffAccountController::class, 'store'])->name('store');
            Route::get('/{staffAccount}/edit', [StaffAccountController::class, 'edit'])->name('edit');
            Route::put('/{staffAccount}', [StaffAccountController::class, 'update'])->name('update');
        });

    Route::prefix('/panel/settings/languages')
        ->name('panel.languages.')
        ->middleware('staff.permission:'.CorePermission::ManageStoreSettings->value)
        ->group(function (): void {
            Route::post('/', [LanguageController::class, 'store'])->name('store');
            Route::put('/{language}', [LanguageController::class, 'update'])->name('update');
        });

    Route::get('/panel/settings', [StoreSettingsController::class, 'index'])->name('panel.settings.index');

    Route::prefix('/panel/settings')
        ->name('panel.settings.')
        ->middleware('staff.permission:'.CorePermission::ManageStoreSettings->value)
        ->group(function (): void {
            Route::put('/', [StoreSettingsController::class, 'update'])->name('update');
            Route::post('/logo', [StoreSettingsController::class, 'uploadLogo'])->name('logo.store');
            Route::delete('/logo', [StoreSettingsController::class, 'removeLogo'])->name('logo.destroy');
            Route::put('/html-filter', [StoreSettingsController::class, 'updateHtmlFilter'])->name('html-filter.update');
            Route::post('/html-filter/sanitize', [StoreSettingsController::class, 'sanitizeStoredHtml'])->name('html-filter.sanitize');
            Route::post('/cache/clear', ClearApplicationCacheController::class)->name('cache.clear');
            Route::post('/maintenance', [MaintenanceController::class, 'enable'])->name('maintenance.enable');
            Route::delete('/maintenance', [MaintenanceController::class, 'disable'])->name('maintenance.disable');
            Route::post('/scheduler/cron', [SchedulerSettingsController::class, 'configureCron'])->name('scheduler.cron');
            Route::post('/demo-data', [DemoDataController::class, 'store'])->name('demo-data.store');
            Route::delete('/demo-data', [DemoDataController::class, 'destroy'])->name('demo-data.destroy');
        });

    Route::prefix('/panel/settings/updates')
        ->name('panel.settings.updates.')
        ->middleware('staff.permission:'.CorePermission::ManageUpdates->value)
        ->group(function (): void {
            Route::post('/check', [UpdatesController::class, 'check'])->name('check');
            Route::put('/automatic', [UpdatesController::class, 'setAutomatic'])->name('automatic');
            Route::post('/install', [UpdatesController::class, 'install'])->name('install');
            Route::post('/advance', [UpdatesController::class, 'advance'])->name('advance');
        });

    Route::get('/panel/settings/tabs/{tab}', [RenderedSettingsTabController::class, 'show'])
        ->name('panel.settings.tabs.show');

    Route::prefix('/panel/settings/email')
        ->name('panel.email.')
        ->middleware('staff.permission:'.CorePermission::ManageEmail->value)
        ->group(function (): void {
            Route::put('/', [EmailSettingsController::class, 'update'])->name('update');
            Route::put('/templates/{definition}/{locale}', [EmailSettingsController::class, 'updateTemplate'])->name('templates.update');
            Route::post('/templates/{definition}/preview', [EmailSettingsController::class, 'previewTemplate'])->name('templates.preview');
            Route::delete('/templates/{definition}/{locale}', [EmailSettingsController::class, 'resetTemplate'])->name('templates.reset');
            Route::post('/layouts', [EmailSettingsController::class, 'storeLayout'])->name('layouts.store');
            Route::put('/layouts/{layout}', [EmailSettingsController::class, 'updateLayout'])->whereNumber('layout')->name('layouts.update');
            Route::delete('/layouts/{layout}', [EmailSettingsController::class, 'destroyLayout'])->whereNumber('layout')->name('layouts.destroy');
            Route::post('/test', [EmailSettingsController::class, 'test'])->name('test');
            Route::post('/messages/{message}/retry', [EmailSettingsController::class, 'retry'])->whereUuid('message')->name('messages.retry');
            Route::post('/messages/{message}/cancel', [EmailSettingsController::class, 'cancel'])->whereUuid('message')->name('messages.cancel');
        });

    // Write-only: only Catalog may touch catalog_seo_settings, but the merchant
    // reads/submits this from the SEO tab on "/panel/settings" — see
    // Catalog\Presentation\Controllers\CatalogSeoSettingsController's docblock.
    Route::put('/panel/catalog/seo-settings', [CatalogSeoSettingsController::class, 'update'])
        ->middleware('staff.permission:'.CorePermission::ManageStoreSettings->value)
        ->name('panel.catalog.seo-settings.update');

    Route::prefix('/panel/settings/redirects')
        ->name('panel.redirects.')
        ->middleware('staff.permission:'.CorePermission::ManageStoreSettings->value)
        ->group(function (): void {
            Route::post('/', [RedirectController::class, 'store'])->name('store');
            Route::put('/{redirect}', [RedirectController::class, 'update'])->name('update');
            Route::delete('/{redirect}', [RedirectController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('/panel/catalog/products')
        ->name('panel.catalog.products.')
        ->middleware('staff.permission:'.CorePermission::ManageCatalog->value)
        ->group(function (): void {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/references', [CatalogReferenceController::class, 'index'])->name('references.index');
            Route::post('/references/brands', [CatalogReferenceController::class, 'storeBrand'])->name('references.brands.store');
            Route::put('/references/brands/{brand}', [CatalogReferenceController::class, 'updateBrand'])->name('references.brands.update');
            Route::post('/references/brands/{brand}/logo', [CatalogReferenceController::class, 'uploadBrandLogo'])->name('references.brands.logo.store');
            Route::delete('/references/brands/{brand}/logo', [CatalogReferenceController::class, 'removeBrandLogo'])->name('references.brands.logo.destroy');
            Route::post('/references/categories', [CatalogReferenceController::class, 'storeCategory'])->name('references.categories.store');
            Route::put('/references/categories/{category}', [CatalogReferenceController::class, 'updateCategory'])->name('references.categories.update');
            Route::post('/references/categories/{category}/image', [CatalogReferenceController::class, 'uploadCategoryImage'])->name('references.categories.image.store');
            Route::delete('/references/categories/{category}/image', [CatalogReferenceController::class, 'removeCategoryImage'])->name('references.categories.image.destroy');
            Route::post('/references/attributes', [CatalogReferenceController::class, 'storeAttribute'])->name('references.attributes.store');
            Route::post('/references/attributes/{attributeDefinition}/values', [CatalogReferenceController::class, 'storeAttributeValue'])->name('references.attribute-values.store');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('update');
            Route::post('/{product}/variants/generate', [ProductController::class, 'generateVariants'])->name('variants.generate');
            Route::put('/{product}/variants/{variant}', [ProductController::class, 'updateVariant'])->name('variants.update');
            Route::put('/{product}/attributes', [ProductController::class, 'setAttributes'])->name('attributes.update');
            Route::post('/{product}/images', [ProductController::class, 'uploadImage'])->name('images.store');
            Route::delete('/{product}/images/{mediaId}', [ProductController::class, 'removeImage'])->name('images.destroy');
            Route::put('/{product}/images/order', [ProductController::class, 'reorderImages'])->name('images.reorder');
            Route::post('/{product}/variants/{variant}/digital-files', [ProductController::class, 'uploadDigitalFile'])->name('variants.digital-files.store');
            Route::put('/{product}/variants/{variant}/digital-files/order', [ProductController::class, 'reorderDigitalFiles'])->name('variants.digital-files.reorder');
            Route::post('/{product}/variants/{variant}/digital-files/{file}/versions', [ProductController::class, 'uploadDigitalFileVersion'])->whereNumber('file')->name('variants.digital-files.versions.store');
            Route::put('/{product}/variants/{variant}/digital-files/{file}', [ProductController::class, 'updateDigitalFile'])->whereNumber('file')->name('variants.digital-files.update');
            Route::get('/{product}/relations/search', [ProductController::class, 'searchRelationCandidates'])->name('relations.search');
            Route::put('/{product}/relations', [ProductController::class, 'setRelations'])->name('relations.update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');

            // Price/VAT editing needs its own permission on top of catalog
            // access — a content editor has ManageCatalog but must not be
            // able to change prices or tax assignment through this page.
            Route::middleware('staff.permission:'.CorePermission::ManagePricing->value)->group(function (): void {
                Route::put('/{product}/tax', [ProductController::class, 'updateTax'])->name('tax.update');
                Route::put('/{product}/variants/{variant}/price', [ProductController::class, 'updateVariantPrice'])->name('variants.price.update');
                Route::put('/{product}/variants/{variant}/price/{currencyCode}', [ProductController::class, 'updateVariantPriceOverride'])->name('variants.price.override.update');
            });
        });

    Route::prefix('/panel/settings/pricing')
        ->name('panel.pricing.')
        ->middleware('staff.permission:'.CorePermission::ManagePricing->value)
        ->group(function (): void {
            Route::put('/settings', [PricingController::class, 'updateSettings'])->name('settings.update');
            Route::post('/currencies', [CurrencyController::class, 'store'])->name('currencies.store');
            Route::put('/currencies/{code}/rate', [PricingController::class, 'setExchangeRate'])->name('currencies.rate.update');
            Route::post('/tax-rates', [PricingController::class, 'storeTaxRate'])->name('tax-rates.store');
            Route::put('/tax-rates/{taxRate}', [PricingController::class, 'updateTaxRate'])->name('tax-rates.update');
        });

    Route::prefix('/panel/settings/delivery')
        ->name('panel.delivery.')
        ->middleware('staff.permission:'.CorePermission::ManageDelivery->value)
        ->group(function (): void {
            Route::post('/', [DeliveryController::class, 'store'])->name('store');
            Route::put('/plugin-methods/{methodId}', [DeliveryController::class, 'updatePluginMethod'])->where('methodId', '[A-Za-z0-9._-]+')->name('plugin-methods.update');
            Route::put('/{deliveryMethod}', [DeliveryController::class, 'update'])->name('update');
            Route::delete('/{deliveryMethod}', [DeliveryController::class, 'destroy'])->name('destroy');
            Route::put('/{deliveryMethod}/region-rules', [DeliveryController::class, 'updateRegionRules'])->name('region-rules.update');
            Route::put('/{deliveryMethod}/currencies/{currencyCode}', [DeliveryController::class, 'updateCurrencyOverride'])->whereAlpha('currencyCode')->name('currencies.update');
            Route::post('/{deliveryMethod}/pickup-points', [DeliveryController::class, 'storePickupPoint'])->name('pickup-points.store');
            Route::put('/{deliveryMethod}/pickup-points/{pickupPoint}', [DeliveryController::class, 'updatePickupPoint'])->name('pickup-points.update');
            Route::delete('/{deliveryMethod}/pickup-points/{pickupPoint}', [DeliveryController::class, 'destroyPickupPoint'])->name('pickup-points.destroy');
        });

    Route::prefix('/panel/coupons')
        ->name('panel.coupons.')
        ->middleware('staff.permission:'.CorePermission::ManageCoupons->value)
        ->group(function (): void {
            Route::get('/', [CouponController::class, 'index'])->name('index');
            Route::post('/', [CouponController::class, 'store'])->name('store');
            Route::put('/settings', [CouponController::class, 'updateSettings'])->name('settings.update');
            Route::get('/{coupon}', [CouponController::class, 'show'])->name('show');
            Route::put('/{coupon}', [CouponController::class, 'update'])->name('update');
            Route::post('/{coupon}/top-up', [CouponController::class, 'topUp'])->name('top-up');
            Route::post('/{coupon}/reset-pin', [CouponController::class, 'resetPin'])->name('reset-pin');
        });

    Route::prefix('/panel/cart')
        ->name('panel.cart.')
        ->middleware('staff.permission:'.CorePermission::ViewOrders->value)
        ->group(function (): void {
            Route::get('/', [CartOverviewController::class, 'index'])->name('index');
            Route::get('/{cartId}', [CartOverviewController::class, 'show'])->whereNumber('cartId')->name('show');
        });

    Route::prefix('/panel/orders')
        ->name('panel.orders.')
        ->middleware('staff.permission:'.CorePermission::ViewOrders->value)
        ->group(function (): void {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/{order}', [OrderController::class, 'show'])->whereUuid('order')->name('show');
            Route::middleware('staff.permission:'.CorePermission::ManageOrders->value)->group(function (): void {
                Route::post('/{order}/payments/manual', [OrderController::class, 'confirmManualPayment'])->whereUuid('order')->name('payments.manual');
                Route::post('/{order}/payments/retry', [OrderController::class, 'retryPayment'])->whereUuid('order')->name('payments.retry');
                Route::post('/{order}/fulfillment/ready', [OrderFulfillmentController::class, 'markReady'])->whereUuid('order')->name('fulfillment.ready');
                Route::post('/{order}/fulfillment/handover', [OrderFulfillmentController::class, 'handOver'])->whereUuid('order')->name('fulfillment.handover');
                Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->whereUuid('order')->name('cancel');
                Route::put('/{order}/status', [OrderController::class, 'updateStatus'])->whereUuid('order')->name('status.update');
                Route::post('/{order}/notes', [OrderController::class, 'addNote'])->whereUuid('order')->name('notes.store');
                Route::post('/{order}/customer-messages', [OrderController::class, 'sendCustomerMessage'])->whereUuid('order')->name('customer-messages.store');
                Route::post('/{order}/digital-access/grant', [OrderDigitalGrantController::class, 'grantAfterLatePayment'])->whereUuid('order')->name('digital-access.grant');
                Route::put('/{order}/digital-access/{grant}/extend', [OrderController::class, 'extendDigitalAccess'])->whereUuid('order')->whereNumber('grant')->name('digital-access.extend');
                Route::put('/{order}/digital-access/{grant}/limit', [OrderController::class, 'setDigitalDownloadLimit'])->whereUuid('order')->whereNumber('grant')->name('digital-access.limit');
                Route::post('/{order}/digital-access/{grant}/revoke', [OrderController::class, 'revokeDigitalAccess'])->whereUuid('order')->whereNumber('grant')->name('digital-access.revoke');
            });

            // Filing a return needs its own permission on top of order
            // access — a warehouse worker has ManageOrders but must not be
            // able to open a return/refund from this page.
            Route::middleware('staff.permission:'.CorePermission::ManageRefunds->value)->group(function (): void {
                Route::get('/{order}/returns/create', [ReturnController::class, 'create'])->whereUuid('order')->name('returns.create');
                Route::post('/{order}/returns', [ReturnController::class, 'store'])->whereUuid('order')->name('returns.store');
            });
        });

    Route::prefix('/panel/returns')
        ->name('panel.returns.')
        ->middleware('staff.permission:'.CorePermission::ManageRefunds->value)
        ->group(function (): void {
            Route::get('/', [ReturnController::class, 'index'])->name('index');
            Route::get('/{returnRequest}', [ReturnController::class, 'show'])->whereUuid('returnRequest')->name('show');
            Route::post('/{returnRequest}/decide', [ReturnController::class, 'decide'])->whereUuid('returnRequest')->name('decide');
            Route::post('/{returnRequest}/refunds/{refund}/payout', [ReturnController::class, 'payout'])->whereUuid('returnRequest')->whereNumber('refund')->name('refunds.payout');
            Route::post('/reasons', [ReturnController::class, 'storeReason'])->name('reasons.store');
            Route::put('/reasons/{reason}', [ReturnController::class, 'updateReason'])->name('reasons.update');
        });

    Route::prefix('/panel/settings/payments')
        ->name('panel.payment-settings.')
        ->middleware('staff.permission:'.CorePermission::ManageOrders->value)
        ->group(function (): void {
            Route::put('/{currencyCode}', [PaymentSettingsController::class, 'update'])->whereAlpha('currencyCode')->name('update');
        });

    Route::prefix('/panel/warehouse')
        ->name('panel.warehouse.')
        ->middleware('staff.permission:'.CorePermission::ManageInventory->value)
        ->group(function (): void {
            Route::get('/', [WarehouseController::class, 'index'])->name('index');
            Route::put('/variants/{variantId}/backorder', [WarehouseController::class, 'updateBackorder'])->name('variants.backorder.update');
            Route::post('/variants/{variantId}/receive', [WarehouseController::class, 'receive'])->name('variants.receive');
            Route::put('/variants/{variantId}/correct', [WarehouseController::class, 'correct'])->name('variants.correct');
        });

    Route::prefix('/panel/extensions')
        ->name('panel.extensions.')
        ->middleware('staff.permission:'.CorePermission::ManageExtensions->value)
        ->group(function (): void {
            Route::get('/', [ExtensionController::class, 'index'])->name('index');
            Route::post('/', [ExtensionController::class, 'store'])->name('store');
            Route::get('/{extension}', [ExtensionController::class, 'show'])->name('show');
            Route::put('/{extension}/settings', [ExtensionController::class, 'updateSettings'])->name('settings.update');
            Route::post('/{extension}/install', [ExtensionController::class, 'install'])->name('install');
            Route::post('/{extension}/enable', [ExtensionController::class, 'enable'])->name('enable');
            Route::post('/{extension}/disable', [ExtensionController::class, 'disable'])->name('disable');
            Route::delete('/{extension}', [ExtensionController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('/panel/themes')
        ->name('panel.themes.')
        ->middleware('staff.permission:'.CorePermission::ManageThemes->value)
        ->group(function (): void {
            Route::get('/', [ThemeController::class, 'index'])->name('index');
            Route::post('/', [ThemeController::class, 'store'])->name('store');
            Route::get('/{theme}', [ThemeController::class, 'show'])->name('show');
            Route::put('/{theme}/settings', [ThemeController::class, 'updateSettings'])->name('settings.update');
            Route::post('/{theme}/activate', [ThemeController::class, 'activate'])->name('activate');
            Route::post('/{theme}/deactivate', [ThemeController::class, 'deactivate'])->name('deactivate');
            Route::delete('/{theme}', [ThemeController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('/panel/cms/pages')
        ->name('panel.cms.pages.')
        ->middleware('staff.permission:'.CorePermission::ManageContent->value)
        ->group(function (): void {
            Route::get('/', [PageController::class, 'index'])->name('index');
            Route::get('/create', [PageController::class, 'create'])->name('create');
            Route::post('/', [PageController::class, 'store'])->name('store');
            Route::get('/products/search', [PageController::class, 'searchProducts'])->name('products.search');
            Route::get('/{page}/edit', [PageController::class, 'edit'])->name('edit');
            Route::put('/{page}', [PageController::class, 'save'])->name('update');
            Route::put('/{page}/homepage', [PageController::class, 'setHomepage'])->name('homepage.update');
            Route::post('/{page}/archive', [PageController::class, 'archive'])->name('archive');
        });
    Route::prefix('/panel/media')
        ->name('panel.media.')
        ->middleware('staff.permission:'.CorePermission::ManageContent->value.','.CorePermission::ManageCatalog->value)
        ->group(function (): void {
            Route::get('/', [MediaPickerController::class, 'index'])->name('index');
            Route::post('/', [MediaPickerController::class, 'store'])->name('store');
        });
    Route::post('/panel/cms/media', [PageController::class, 'uploadImage'])
        ->middleware('staff.permission:'.CorePermission::ManageContent->value)
        ->name('panel.cms.media.store');
    Route::post('/panel/cms/reset-to-theme-defaults', [PageController::class, 'resetToThemeDefaults'])
        ->middleware('staff.permission:'.CorePermission::ManageContent->value)
        ->name('panel.cms.reset-to-theme-defaults');

    Route::prefix('/panel/cms/archetypes')
        ->name('panel.cms.archetypes.')
        ->middleware('staff.permission:'.CorePermission::ManageContent->value)
        ->group(function (): void {
            Route::get('/', [ArchetypeTemplateController::class, 'index'])->name('index');
            Route::get('/{key}/edit', [ArchetypeTemplateController::class, 'edit'])->name('edit');
            Route::put('/{key}', [ArchetypeTemplateController::class, 'save'])->name('update');
            Route::post('/{key}/restore-default', [ArchetypeTemplateController::class, 'restoreDefault'])->name('restore');
        });

    Route::prefix('/panel/cms/menus')
        ->name('panel.cms.menus.')
        ->middleware('staff.permission:'.CorePermission::ManageContent->value)
        ->group(function (): void {
            Route::get('/', [MenuController::class, 'index'])->name('index');
            Route::post('/', [MenuController::class, 'store'])->name('store');
            Route::get('/{menu}/edit', [MenuController::class, 'edit'])->name('edit');
            Route::put('/{menu}', [MenuController::class, 'update'])->name('update');
            Route::put('/{menu}/items', [MenuController::class, 'updateItems'])->name('items.update');
            Route::delete('/{menu}', [MenuController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('/panel/blog')
        ->name('panel.blog.')
        ->middleware('staff.permission:'.CorePermission::ManageContent->value)
        ->group(function (): void {
            Route::get('/', [BlogPostController::class, 'index'])->name('posts.index');
            Route::get('/create', [BlogPostController::class, 'create'])->name('posts.create');
            Route::post('/', [BlogPostController::class, 'store'])->name('posts.store');
            Route::get('/categories', [BlogCategoryController::class, 'index'])->name('categories.index');
            Route::post('/categories', [BlogCategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [BlogCategoryController::class, 'update'])->name('categories.update');
            Route::post('/categories/{category}/archive', [BlogCategoryController::class, 'archive'])->name('categories.archive');
            Route::post('/{post}/featured-image', [BlogPostController::class, 'uploadFeaturedImage'])->name('posts.featured-image.store');
            Route::delete('/{post}/featured-image', [BlogPostController::class, 'removeFeaturedImage'])->name('posts.featured-image.destroy');
            Route::get('/{post}/edit', [BlogPostController::class, 'edit'])->name('posts.edit');
            Route::put('/{post}', [BlogPostController::class, 'update'])->name('posts.update');
            Route::post('/{post}/archive', [BlogPostController::class, 'archive'])->name('posts.archive');
        });

    Route::middleware('password.confirm')->group(function () {
        Route::post('/profile/two-factor', [StaffTwoFactorSettingsController::class, 'store'])
            ->name('profile.two-factor.store');
        Route::post('/profile/two-factor/confirm', [StaffTwoFactorSettingsController::class, 'confirm'])
            ->name('profile.two-factor.confirm');
        Route::delete('/profile/two-factor', [StaffTwoFactorSettingsController::class, 'destroy'])
            ->name('profile.two-factor.destroy');
    });
});

// Registered LAST: storefront-pages.php's "/" and "/{locale}" catch every
// path not already claimed by a route above (/install, /login, /customer,
// /cart, /checkout, /panel, /profile, ...) — Laravel matches routes in
// registration order, so those keep taking priority over the wildcard.
require __DIR__.'/storefront-pages.php';
