<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Store\Application\ConfigureStoreBootstrap;
use Litecommerce\Modules\Store\Application\LocalizedAddressFormatter;
use Litecommerce\Modules\Store\Application\LocalizedDateTimeFormatter;
use Litecommerce\Modules\Store\Application\LocalizedMoneyFormatter;
use Litecommerce\Modules\Store\Application\LocalizedPercentageFormatter;
use Litecommerce\Modules\Store\Application\LocalizedPhoneFormatter;
use Litecommerce\Modules\Store\Application\LocalizedQuantityFormatter;
use Litecommerce\Modules\Store\Application\SanitizeStoredHtml;
use Litecommerce\Modules\Store\Console\SanitizeStoredHtmlCommand;
use Litecommerce\Modules\Store\Contracts\AddressFormatter;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\DateTimeFormatter;
use Litecommerce\Modules\Store\Contracts\Events\LanguagesChanged;
use Litecommerce\Modules\Store\Contracts\Events\StoreSettingsChanged;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\MoneyFormatter;
use Litecommerce\Modules\Store\Contracts\PercentageFormatter;
use Litecommerce\Modules\Store\Contracts\PhoneFormatter;
use Litecommerce\Modules\Store\Contracts\QuantityFormatter;
use Litecommerce\Modules\Store\Contracts\SettingsTabRegistry;
use Litecommerce\Modules\Store\Contracts\SetupChecklist;
use Litecommerce\Modules\Store\Contracts\StoreBootstrap;
use Litecommerce\Modules\Store\Contracts\StoreCacheGroups;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;
use Litecommerce\Modules\Store\Infrastructure\InMemorySettingsTabRegistry;
use Litecommerce\Modules\Store\Infrastructure\InMemorySetupChecklist;
use Litecommerce\Modules\Store\Infrastructure\Repositories\EloquentCurrencyRepository;
use Litecommerce\Modules\Store\Infrastructure\Repositories\EloquentLanguageRepository;
use Litecommerce\Modules\Store\Infrastructure\Repositories\EloquentStoreSettingsReader;
use Litecommerce\Modules\Store\Infrastructure\Repositories\EloquentStoreSettingsStore;
use Litecommerce\Modules\Store\Infrastructure\SymfonyHtmlContentFilter;
use Litecommerce\Modules\Store\Presentation\Controllers\LanguageController;
use Litecommerce\Modules\Store\Presentation\Controllers\SeoSettingsController;
use Litecommerce\Modules\Store\Presentation\Controllers\StoreSettingsController;

final class StoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CurrencyRepository::class, EloquentCurrencyRepository::class);
        $this->app->scoped(MoneyFormatter::class, LocalizedMoneyFormatter::class);
        $this->app->scoped(DateTimeFormatter::class, LocalizedDateTimeFormatter::class);
        $this->app->scoped(QuantityFormatter::class, LocalizedQuantityFormatter::class);
        $this->app->scoped(AddressFormatter::class, LocalizedAddressFormatter::class);
        $this->app->scoped(PhoneFormatter::class, LocalizedPhoneFormatter::class);
        $this->app->scoped(PercentageFormatter::class, LocalizedPercentageFormatter::class);
        $this->app->bind(LanguageRepository::class, EloquentLanguageRepository::class);
        $this->app->bind(StoreBootstrap::class, ConfigureStoreBootstrap::class);
        $this->app->bind(StoreSettingsReader::class, EloquentStoreSettingsReader::class);
        $this->app->bind(StoreSettingsStore::class, EloquentStoreSettingsStore::class);
        $this->app->singleton(HtmlContentFilter::class, SymfonyHtmlContentFilter::class);
        $this->app->bind(SanitizeStoredHtml::class, fn ($app): SanitizeStoredHtml => new SanitizeStoredHtml($app->make(HtmlContentFilter::class), $app->tagged('store.html_content')));
        $this->app->singleton(SettingsTabRegistry::class, InMemorySettingsTabRegistry::class);
        $this->app->singleton(SetupChecklist::class, InMemorySetupChecklist::class);
    }

    public function boot(): void
    {
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('store.currency.');
        $this->commands([SanitizeStoredHtmlCommand::class]);
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'store');

        Event::listen(StoreSettingsChanged::class, function (): void {
            $this->app->make(ObjectCache::class)->invalidateGroup(StoreCacheGroups::SETTINGS);
        });
        Event::listen(LanguagesChanged::class, function (): void {
            $this->app->make(ObjectCache::class)->invalidateGroup(StoreCacheGroups::LANGUAGES);
        });

        $registry = $this->app->make(GlobalTagRegistry::class);
        $registry->register('store_name', trans('store::panel.tags.store_name'), fn (): string => $this->app->make(StoreSettingsReader::class)->current()->storeName);
        $registry->register('store_logo_url', trans('store::panel.tags.store_logo_url'), fn (): string => $this->app->make(StoreSettingsReader::class)->current()->logoUrl ?? '');

        $tabs = $this->app->make(SettingsTabRegistry::class);
        $can = fn (CorePermission $permission): \Closure => fn (): bool => auth()->id() !== null
            && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), $permission->value);
        $tabs->register('general', 'store::tabs.general', 100, [$this->app->make(StoreSettingsController::class), 'generalTab'], $can(CorePermission::ManageStoreSettings));
        $tabs->register('languages', 'store::tabs.languages', 400, [$this->app->make(LanguageController::class), 'settingsTab'], $can(CorePermission::ManageStoreSettings));
        $tabs->register('seo', 'store::tabs.seo', 500, [$this->app->make(SeoSettingsController::class), 'settingsTab'], $can(CorePermission::ManageStoreSettings));
        $tabs->register('advanced', 'store::tabs.advanced', 900, [$this->app->make(StoreSettingsController::class), 'advancedTab'], $can(CorePermission::ManageStoreSettings));
    }
}
