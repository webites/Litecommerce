<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Blog\Contracts\Events\BlogContentChanged;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Store\Contracts\Events\LanguagesChanged;
use Litecommerce\Modules\Store\Contracts\Events\StoreSettingsChanged;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Storefront\Application\FlushSitemapCache;
use Litecommerce\Modules\Storefront\Contracts\LanguageAlternateResolver;
use Litecommerce\Modules\Storefront\Contracts\MenuLocationRegistry;
use Litecommerce\Modules\Storefront\Contracts\MenuRenderer;
use Litecommerce\Modules\Storefront\Contracts\MenuWidgetRegistry;
use Litecommerce\Modules\Storefront\Contracts\ProductListingItemAssembler;
use Litecommerce\Modules\Storefront\Contracts\ProductSearchListingBuilder;
use Litecommerce\Modules\Storefront\Contracts\ProductTabRegistry;
use Litecommerce\Modules\Storefront\Contracts\StorefrontSlotRegistry;
use Litecommerce\Modules\Storefront\Contracts\ThemeRegionComposer;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;
use Litecommerce\Modules\Storefront\Infrastructure\BladeThemeRenderer;
use Litecommerce\Modules\Storefront\Infrastructure\ConfigDrivenThemeRegionComposer;
use Litecommerce\Modules\Storefront\Infrastructure\DefaultProductSearchListingBuilder;
use Litecommerce\Modules\Storefront\Infrastructure\InMemoryMenuLocationRegistry;
use Litecommerce\Modules\Storefront\Infrastructure\InMemoryMenuWidgetRegistry;
use Litecommerce\Modules\Storefront\Infrastructure\InMemoryProductTabRegistry;
use Litecommerce\Modules\Storefront\Infrastructure\InMemoryStorefrontSlotRegistry;
use Litecommerce\Modules\Storefront\Infrastructure\PricingAwareProductListingItemAssembler;
use Litecommerce\Modules\Storefront\Infrastructure\RouteAwareLanguageAlternateResolver;
use Litecommerce\Modules\Storefront\Infrastructure\RouteAwareMenuResolver;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;

final class StorefrontServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/../Infrastructure/theme-helpers.php';

        $this->app->bind(ThemeRenderer::class, BladeThemeRenderer::class);
        $this->app->singleton(StorefrontSlotRegistry::class, InMemoryStorefrontSlotRegistry::class);
        $this->app->singleton(ProductTabRegistry::class, InMemoryProductTabRegistry::class);
        $this->app->singleton(ThemeRegionComposer::class, ConfigDrivenThemeRegionComposer::class);
        $this->app->bind(ProductListingItemAssembler::class, PricingAwareProductListingItemAssembler::class);
        $this->app->bind(ProductSearchListingBuilder::class, DefaultProductSearchListingBuilder::class);
        // Singleton: caches its own result per request (see the class docblock) —
        // the view composer below calls it once per theme:: view render.
        $this->app->singleton(LanguageAlternateResolver::class, RouteAwareLanguageAlternateResolver::class);
        $this->app->singleton(MenuWidgetRegistry::class, InMemoryMenuWidgetRegistry::class);
        $this->app->singleton(MenuLocationRegistry::class, InMemoryMenuLocationRegistry::class);
        $this->app->singleton(MenuRenderer::class, RouteAwareMenuResolver::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'storefront');

        // Renders a plugin-registered fragment; unescaped on purpose, see StorefrontSlotRegistry.
        Blade::directive('storefrontSlot', fn (string $expression): string => "<?php echo app(\Litecommerce\Modules\Storefront\Contracts\StorefrontSlotRegistry::class)->render({$expression}); ?>");

        Blade::directive('themeRegions', fn (string $expression): string => "<?php echo app(\Litecommerce\Modules\Storefront\Contracts\ThemeRegionComposer::class)->render({$expression}); ?>");

        $this->registerCoreMenuWidgets();

        $flush = fn () => $this->app->make(FlushSitemapCache::class)->handle();
        Event::listen(CatalogContentChanged::class, $flush);
        Event::listen(CmsContentChanged::class, $flush);
        Event::listen(BlogContentChanged::class, $flush);
        Event::listen(LanguagesChanged::class, $flush);
        Event::listen(StoreSettingsChanged::class, $flush);

        // Every theme:: view gets $themeAssetUrl/$themeSupportsDarkMode/$themeMode without each controller passing them explicitly.
        // $themeMode is "dark" or null — the theme's own :root is light by default, so "no attribute" already means light.
        // $themeTranslations is the small set a theme's own client-side JS (e.g. React islands) may need — resolved
        // per-request like everything else here, so it follows the visited /{locale} the same way Blade's trans() does.
        // $languages/$languageAlternates power the storefront language switcher
        // partial (theme::partials.language-switcher) — a plain list of enabled
        // languages, plus the equivalent URL of THIS page in each one, computed
        // once per request by RouteAwareLanguageAlternateResolver. Skipped
        // entirely (both stay empty) for the common single-language store, so
        // no extra DB work happens unless a second language actually exists.
        View::composer('theme::*', function ($view): void {
            $resolver = $this->app->make(ActiveThemeResolver::class);
            $request = $this->app->make(Request::class);
            $supportsDarkMode = $resolver->supportsDarkMode();
            $view->with('themeAssetUrl', static fn (string $path): ?string => $resolver->assetUrl($path));
            $view->with('themeSupportsDarkMode', $supportsDarkMode);
            $view->with('themeMode', $supportsDarkMode && $request->cookie('storefront_theme') === 'dark' ? 'dark' : null);
            $view->with('themeTranslations', [
                'cart' => trans('cart::cart'),
                'checkout' => trans('cart::checkout'),
                'product' => trans('catalog::product'),
                'marketing' => trans('consents::marketing'),
            ]);
            $view->with('storeSettings', $this->app->make(StoreSettingsReader::class)->current());

            $enabledLanguages = array_values(array_filter(
                $this->app->make(LanguageRepository::class)->all(),
                static fn (LanguageData $language): bool => $language->isEnabled,
            ));
            $currentLocale = (string) ($request->route()?->parameter('locale') ?? app()->getLocale());
            $view->with('locale', $currentLocale);
            $view->with('languages', array_map(
                static fn (LanguageData $language): array => ['code' => $language->code, 'name' => $language->name],
                $enabledLanguages,
            ));
            $view->with('languageAlternates', count($enabledLanguages) > 1
                ? $this->app->make(LanguageAlternateResolver::class)->resolve($request, $currentLocale)
                : []);

            // $defaultLocaleCode/$hreflangSelfPath power the <link rel="alternate"
            // hreflang> tags in theme::layout — self-reference and x-default need
            // THIS page's own path, which $languageAlternates never carries (it's
            // only ever the OTHER locales, see RouteAwareLanguageAlternateResolver's
            // docblock). The product route is special-cased to the {slug} route
            // parameter alone, so a variant deep-link's extra path segments never
            // leak into the self/x-default tag — the same "point at the base
            // product URL" simplification $languageAlternates already applies to
            // product pages via productAlternates().
            $defaultLocaleCode = $currentLocale;
            $languageNoindex = false;
            foreach ($enabledLanguages as $language) {
                if ($language->isDefault) {
                    $defaultLocaleCode = $language->code;
                }
                if ($language->code === $currentLocale) {
                    $languageNoindex = $language->noindex;
                }
            }
            $view->with('defaultLocaleCode', $defaultLocaleCode);
            // Drives <meta name="robots" content="noindex"> in theme::layout for
            // EVERY page under this language — combined there with a page's own
            // @section('robots', 'noindex') (e.g. a product's variant_seo_mode).
            $view->with('languageNoindex', $languageNoindex);

            $route = $request->route();
            $view->with('hreflangSelfPath', $route !== null && $route->getName() === 'storefront.catalog.show'
                ? "/{$currentLocale}/products/".$route->parameter('slug')
                : '/'.ltrim($request->path(), '/'));

            // $navMenus powers theme::partials.nav-menu — one resolved tree per
            // declared location. 'primary' is always included even when the
            // active theme's manifest doesn't (yet) declare it, so
            // RouteAwareMenuResolver's own fallback-to-core-widgets safety net
            // always has a chance to run — see that class's docblock.
            $locationIds = array_values(array_unique(array_merge(
                ['primary'],
                array_column($resolver->menuLocations(), 'id'),
                array_column($this->app->make(MenuLocationRegistry::class)->all(), 'id'),
            )));
            $menuRenderer = $this->app->make(MenuRenderer::class);
            $view->with('navMenus', array_combine(
                $locationIds,
                array_map(static fn (string $locationId): array => $menuRenderer->render($request, $locationId, $currentLocale), $locationIds),
            ));
        });
    }

    /**
     * Core's own 6 addable "widget" menu items — cart/account delegate to the
     * partials extracted from layout.blade.php's old inline markup;
     * language-switcher/theme-mode-toggle delegate to their existing,
     * unchanged partials; catalog_index/home are plain links built inline
     * since they have no partial of their own to reuse. A plugin adds its
     * own the exact same way, from its own PluginServiceProvider::boot() —
     * see examples/hello-plugin for the established pattern.
     */
    private function registerCoreMenuWidgets(): void
    {
        $registry = $this->app->make(MenuWidgetRegistry::class);

        $registry->register('catalog_index', 'core', trans('storefront::messages.nav_products'), static fn (?string $label): string => sprintf(
            '<a href="%s">%s</a>',
            e(route('storefront.catalog.index', ['locale' => request()->route()?->parameter('locale') ?? app()->getLocale()])),
            e($label ?? trans('storefront::messages.nav_products')),
        ));
        $registry->register('home', 'core', trans('storefront::messages.nav_home'), static fn (?string $label): string => sprintf(
            '<a href="%s">%s</a>',
            e(url('/'.(request()->route()?->parameter('locale') ?? app()->getLocale()))),
            e($label ?? trans('storefront::messages.nav_home')),
        ));
        $registry->register('cart', 'core', trans('storefront::messages.nav_cart'), static fn (?string $label): string => View::make('theme::partials.cart-link', ['labelOverride' => $label])->render());
        $registry->register('account', 'core', trans('storefront::messages.nav_my_account'), static fn (?string $label): string => View::make('theme::partials.account-links', ['labelOverride' => $label])->render());
        $registry->register('language_switcher', 'core', 'Przełącznik języka', static fn (): string => View::make('theme::partials.language-switcher')->render());
        $registry->register('theme_mode_toggle', 'core', 'Przełącznik trybu ciemnego', static fn (): string => View::make('theme::partials.theme-mode-toggle')->render());
    }
}
