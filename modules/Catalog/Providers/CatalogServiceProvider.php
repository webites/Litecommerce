<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Audit\Contracts\AuditRetentionRegistry;
use Litecommerce\Modules\Catalog\Application\CatalogDemoDataContributor;
use Litecommerce\Modules\Catalog\Contracts\BrandCatalog;
use Litecommerce\Modules\Catalog\Contracts\CatalogSeoSettingsRepository;
use Litecommerce\Modules\Catalog\Contracts\CategoryCatalog;
use Litecommerce\Modules\Catalog\Contracts\CoreProductType;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeDescriptor;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Catalog\Contracts\SearchDocumentSource;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Catalog\Infrastructure\InMemoryProductTypeRegistry;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Repositories\EloquentBrandCatalog;
use Litecommerce\Modules\Catalog\Infrastructure\Repositories\EloquentCatalogSeoSettingsRepository;
use Litecommerce\Modules\Catalog\Infrastructure\Repositories\EloquentCategoryCatalog;
use Litecommerce\Modules\Catalog\Infrastructure\Repositories\EloquentProductCatalog;
use Litecommerce\Modules\Catalog\Infrastructure\Repositories\EloquentSearchDocumentSource;
use Litecommerce\Modules\Catalog\Infrastructure\Repositories\EloquentVariantCatalog;
use Litecommerce\Modules\Catalog\Infrastructure\StoredCatalogHtml;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Store\Contracts\SetupChecklist;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProductTypeRegistry::class, static function (): ProductTypeRegistry {
            $registry = new InMemoryProductTypeRegistry;
            $registry->register(new ProductTypeDescriptor(CoreProductType::Physical->value, 'catalog::product_types.physical', tracksStock: true, requiresDelivery: true, grantsDigitalAccess: false, soldAsSingleUnit: false));
            $registry->register(new ProductTypeDescriptor(CoreProductType::Digital->value, 'catalog::product_types.digital', tracksStock: false, requiresDelivery: false, grantsDigitalAccess: true, soldAsSingleUnit: true));

            return $registry;
        });
        $this->app->tag([StoredCatalogHtml::class], 'store.html_content');
        $this->app->bind(VariantCatalog::class, EloquentVariantCatalog::class);
        $this->app->bind(ProductCatalog::class, EloquentProductCatalog::class);
        $this->app->bind(SearchDocumentSource::class, EloquentSearchDocumentSource::class);
        $this->app->bind(CategoryCatalog::class, EloquentCategoryCatalog::class);
        $this->app->bind(BrandCatalog::class, EloquentBrandCatalog::class);
        $this->app->bind(CatalogSeoSettingsRepository::class, EloquentCatalogSeoSettingsRepository::class);
        $this->app->singleton(CatalogDemoDataContributor::class);
        $this->app->tag(CatalogDemoDataContributor::class, DemoDataContributor::class);
    }

    public function boot(): void
    {
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('catalog.variant.price');
        $this->app->make(AuditRetentionRegistry::class)->keepIndefinitely('catalog.product.tax_assigned');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'catalog');

        $this->app->make(SetupChecklist::class)->register(
            'catalog',
            100,
            'catalog::panel.setup_title',
            'catalog::panel.setup_description',
            '/panel/catalog/products/create',
            static fn (): bool => Product::query()->exists(),
            fn (): bool => auth()->id() !== null && $this->app->make(StaffPermissionChecker::class)->allows((int) auth()->id(), CorePermission::ManageCatalog->value),
        );

        // Category/brand archives are the first two registrants of Cms's generic
        // archetype mechanism — see docs/knowledge-base/09-presentation.md.
        $this->app->make(ArchetypeRegistry::class)->register(
            'category',
            'Kategoria',
            fn (string $slug, string $locale) => $this->app->make(CategoryCatalog::class)->matchArchive($slug, $locale),
            availableTags: ['category_name' => 'Nazwa kategorii', 'category_description' => 'Opis kategorii'],
            findSlugInLocale: fn (array $context, string $locale) => is_numeric($context['category_id'] ?? null)
                ? $this->app->make(CategoryCatalog::class)->findSlugInLocale((int) $context['category_id'], $locale)
                : null,
            listInstances: fn (string $locale) => array_map(
                static fn ($category) => ['id' => (string) $category->id, 'label' => $category->name],
                $this->app->make(CategoryCatalog::class)->listAll($locale),
            ),
            resolveInstance: function (string $id, string $locale) {
                $category = $this->app->make(CategoryCatalog::class)->find((int) $id, $locale);

                return $category === null ? null : ['title' => $category->name, 'slug' => $category->slug];
            },
        );
        $this->app->make(ArchetypeRegistry::class)->register(
            'brand',
            'Marka',
            fn (string $slug, string $locale) => $this->app->make(BrandCatalog::class)->matchArchive($slug, $locale),
            availableTags: ['brand_name' => 'Nazwa marki', 'brand_description' => 'Opis marki'],
            findSlugInLocale: fn (array $context, string $locale) => is_numeric($context['brand_id'] ?? null)
                ? $this->app->make(BrandCatalog::class)->findSlugInLocale((int) $context['brand_id'], $locale)
                : null,
            listInstances: fn (string $locale) => array_map(
                static fn ($brand) => ['id' => (string) $brand->id, 'label' => $brand->name],
                $this->app->make(BrandCatalog::class)->listAll($locale),
            ),
            resolveInstance: function (string $id, string $locale) {
                $brand = $this->app->make(BrandCatalog::class)->find((int) $id, $locale);

                return $brand === null ? null : ['title' => $brand->name, 'slug' => $brand->slug];
            },
        );
    }
}
