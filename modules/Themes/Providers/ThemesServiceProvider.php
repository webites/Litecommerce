<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Extensions\Contracts\ExtensionCatalog;
use Litecommerce\Modules\Themes\Application\ActivateTheme;
use Litecommerce\Modules\Themes\Application\DeleteTheme;
use Litecommerce\Modules\Themes\Application\ThemeSeeder;
use Litecommerce\Modules\Themes\Application\UploadThemePackage;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;
use Litecommerce\Modules\Themes\Contracts\Events\ThemeActivated;
use Litecommerce\Modules\Themes\Contracts\ThemeCacheGroups;
use Litecommerce\Modules\Themes\Contracts\ThemeCatalog;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Contracts\ThemeSettingsStore;
use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Domain\ThemeManifestParser;
use Litecommerce\Modules\Themes\Infrastructure\EloquentActiveThemeResolver;
use Litecommerce\Modules\Themes\Infrastructure\EloquentThemeCatalog;
use Litecommerce\Modules\Themes\Infrastructure\EloquentThemeSettingsStore;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;
use Litecommerce\Modules\Themes\Infrastructure\ThemeAssetPublisher;
use Litecommerce\Modules\Themes\Presentation\Console\MakeThemeCommand;

final class ThemesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ThemeCatalog::class, EloquentThemeCatalog::class);
        $this->app->bind(ThemeSettingsStore::class, EloquentThemeSettingsStore::class);

        $this->app->bind(ActiveThemeResolver::class, fn (): ActiveThemeResolver => new EloquentActiveThemeResolver(
            (string) config('themes.public_disk'),
            (string) config('themes.public_path'),
            $this->app->make(ObjectCache::class),
        ));

        $this->app->singleton(ThemeAssetPublisher::class, fn (Application $app): ThemeAssetPublisher => new ThemeAssetPublisher(
            $app->make(Filesystem::class),
            (string) config('themes.disk'), (string) config('themes.path'),
            (string) config('themes.public_disk'), (string) config('themes.public_path'),
        ));

        $this->app->bind(UploadThemePackage::class, fn (Application $app): UploadThemePackage => new UploadThemePackage(
            $app->make(ThemeManifestParser::class),
            (int) config('themes.max_package_size_mb') * 1024 * 1024,
            (int) config('themes.max_file_count'),
            (string) config('themes.disk'),
            (string) config('themes.path'),
        ));

        $this->app->bind(ActivateTheme::class, fn (Application $app): ActivateTheme => new ActivateTheme(
            $app->make(ExtensionCatalog::class),
            $app->make(ThemeAssetPublisher::class),
            (string) config('extensions.core_version'),
        ));

        $this->app->bind(DeleteTheme::class, fn (Application $app): DeleteTheme => new DeleteTheme(
            $app->make(ThemeAssetPublisher::class),
            (string) config('themes.disk'),
            (string) config('themes.path'),
        ));

        $this->app->bind(ThemeInstaller::class, ThemeSeeder::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'themes');

        Event::listen(ThemeActivated::class, function (): void {
            $this->app->make(ObjectCache::class)->invalidateGroup(ThemeCacheGroups::ACTIVE);
        });

        // Dev-time scaffold; ServiceProvider::commands() defers via Artisan::starting(), safe outside console too.
        $this->commands([MakeThemeCommand::class]);

        // Left null (not an early return) rather than short-circuiting, so the replaceNamespace() calls below
        // still run and clear stale hints from a previous boot() in this process (e.g. a queue worker).
        $viewsPath = null;
        $langPath = null;
        $hasActiveTheme = false;

        try {
            if (Schema::hasTable('themes')) {
                $activeId = Theme::query()->where('status', ThemeStatus::Active->value)->value('id');

                if ($activeId !== null) {
                    $hasActiveTheme = true;
                    $themeStoragePath = Storage::disk((string) config('themes.disk'))->path(config('themes.path')."/{$activeId}");
                    $viewsPath = "{$themeStoragePath}/views";
                    $langPath = "{$themeStoragePath}/lang";
                }
            }
        } catch (\Throwable) {
            // Not-yet-installed shop or a database briefly unreachable: no active theme's views/lang get registered.
        }

        // WooCommerce-style fallback: any theme::partials.x missing from the active theme renders
        // default-storefront's own copy instead, since FileViewFinder tries hint paths in order.
        $defaultViewsPath = base_path('resources/themes/default-storefront/views');

        $isDir = static fn (?string $path): bool => $path !== null && is_dir($path);

        // replaceNamespace(), not addNamespace() — the latter appends, so a theme switch within one
        // long-lived process would keep resolving from the stale, previously-active theme first.
        View::replaceNamespace('theme', array_values(array_filter([$viewsPath, $defaultViewsPath], $isDir)));

        // Active-theme-only, no fallback — BladeThemeRenderer uses this to check a TOP-LEVEL view exists.
        View::replaceNamespace('theme-active', $isDir($viewsPath) ? [$viewsPath] : []);

        View::flushFinderCache();

        // No cross-theme fallback here (unlike views): an active theme lacking its own lang/ just resolves
        // empty for theme::, it never silently picks up another theme's theme-specific strings. Shared
        // storefront vocabulary belongs to the storefront:: namespace instead. Translator::addNamespace()
        // stores one hint per call, so this correctly replaces the previous theme's on every switch, same
        // semantics as View::replaceNamespace() above. Falls back to the default theme's own lang/ only
        // when there is no active theme at all (pre-install / DB unreachable).
        Lang::addNamespace('theme', $hasActiveTheme ? $langPath : base_path('resources/themes/default-storefront/lang'));
    }
}
