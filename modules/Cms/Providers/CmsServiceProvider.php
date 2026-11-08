<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Cms\Application\CmsDemoDataContributor;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\ArchetypeResolver;
use Litecommerce\Modules\Cms\Contracts\BlockDataRegistry;
use Litecommerce\Modules\Cms\Contracts\BlockRenderer;
use Litecommerce\Modules\Cms\Contracts\CmsCacheGroups;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Cms\Contracts\GlobalTagRegistry;
use Litecommerce\Modules\Cms\Contracts\MenuRepository;
use Litecommerce\Modules\Cms\Infrastructure\InMemoryArchetypeRegistry;
use Litecommerce\Modules\Cms\Infrastructure\InMemoryBlockDataRegistry;
use Litecommerce\Modules\Cms\Infrastructure\InMemoryGlobalTagRegistry;
use Litecommerce\Modules\Cms\Infrastructure\Repositories\EloquentCmsPageRepository;
use Litecommerce\Modules\Cms\Infrastructure\Repositories\EloquentMenuRepository;
use Litecommerce\Modules\Cms\Infrastructure\StoredCmsHtml;
use Litecommerce\Modules\Cms\Infrastructure\ThemeArchetypeResolver;
use Litecommerce\Modules\Cms\Infrastructure\ThemeBlockRenderer;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;

final class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StoredCmsHtml::class], 'store.html_content');
        $this->app->bind(CmsPageRepository::class, EloquentCmsPageRepository::class);
        $this->app->bind(BlockRenderer::class, ThemeBlockRenderer::class);
        $this->app->singleton(BlockDataRegistry::class, InMemoryBlockDataRegistry::class);
        $this->app->singleton(ArchetypeRegistry::class, InMemoryArchetypeRegistry::class);
        $this->app->bind(ArchetypeResolver::class, ThemeArchetypeResolver::class);
        $this->app->singleton(GlobalTagRegistry::class, InMemoryGlobalTagRegistry::class);
        $this->app->bind(MenuRepository::class, EloquentMenuRepository::class);
        $this->app->singleton(CmsDemoDataContributor::class);
        $this->app->tag(CmsDemoDataContributor::class, DemoDataContributor::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'cms');

        $invalidateMenus = function (): void {
            $this->app->make(ObjectCache::class)->invalidateGroup(CmsCacheGroups::MENUS);
        };
        Event::listen(CmsContentChanged::class, $invalidateMenus);
        Event::listen(CatalogContentChanged::class, $invalidateMenus);
    }
}
