<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\Blog\Application\BlogDemoDataContributor;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Blog\Contracts\BlogPostData;
use Litecommerce\Modules\Blog\Infrastructure\Repositories\EloquentBlogCatalog;
use Litecommerce\Modules\Blog\Infrastructure\StoredBlogHtml;
use Litecommerce\Modules\Cms\Contracts\ArchetypeRegistry;
use Litecommerce\Modules\Cms\Contracts\BlockDataRegistry;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Throwable;

final class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([StoredBlogHtml::class], 'store.html_content');
        $this->app->bind(BlogCatalog::class, EloquentBlogCatalog::class);
        $this->app->singleton(BlogDemoDataContributor::class);
        $this->app->tag(BlogDemoDataContributor::class, DemoDataContributor::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'blog');

        try {
            if (Schema::hasTable('store_settings') && ! $this->app->make(StoreSettingsReader::class)->current()->blogEnabled) {
                return;
            }
        } catch (Throwable) {
            // Installation and recovery must boot even while the database is unavailable.
        }

        $catalog = $this->app->make(BlogCatalog::class);
        $registry = $this->app->make(ArchetypeRegistry::class);
        $this->registerCmsBlocks($catalog, $this->app->make(BlockDataRegistry::class));

        $registry->register(
            'blog',
            'Blog',
            fn (string $slug, string $locale) => $catalog->matchIndexArchive($slug, $locale),
            availableTags: ['blog_title' => 'Tytuł bloga'],
            findSlugInLocale: static fn (array $context, string $locale): string => 'blog',
            listInstances: static fn (string $locale): array => [['id' => 'index', 'label' => trans('blog::messages.title', [], $locale)]],
            resolveInstance: static fn (string $id, string $locale): ?array => $id === 'index'
                ? ['title' => trans('blog::messages.title', [], $locale), 'slug' => 'blog']
                : null,
        );

        $registry->register(
            'blog_category',
            'Kategoria bloga',
            fn (string $slug, string $locale) => $catalog->matchCategoryArchive($slug, $locale),
            availableTags: [
                'blog_category_name' => 'Nazwa kategorii bloga',
                'blog_category_description' => 'Opis kategorii bloga',
            ],
            findSlugInLocale: fn (array $context, string $locale): ?string => is_numeric($context['blog_category_id'] ?? null)
                ? (($slug = $catalog->findCategorySlugInLocale((int) $context['blog_category_id'], $locale)) === null ? null : "blog/category/{$slug}")
                : null,
            listInstances: fn (string $locale): array => array_map(
                static fn ($category): array => ['id' => (string) $category->id, 'label' => $category->name],
                $catalog->listCategories($locale),
            ),
            resolveInstance: function (string $id, string $locale) use ($catalog): ?array {
                $category = $catalog->findCategory((int) $id, $locale);

                return $category === null ? null : ['title' => $category->name, 'slug' => "blog/category/{$category->slug}"];
            },
        );

        $registry->register(
            'blog_post',
            'Wpis blogowy',
            fn (string $slug, string $locale) => $catalog->matchPost($slug, $locale),
            availableTags: [
                'blog_post_title' => 'Tytuł wpisu',
                'blog_post_excerpt' => 'Zajawka wpisu',
            ],
            findSlugInLocale: fn (array $context, string $locale): ?string => is_numeric($context['blog_post_id'] ?? null)
                ? (($slug = $catalog->findPostSlugInLocale((int) $context['blog_post_id'], $locale)) === null ? null : "blog/{$slug}")
                : null,
        );
    }

    private function registerCmsBlocks(BlogCatalog $catalog, BlockDataRegistry $blockData): void
    {
        config()->set('cms.blocks.blog-post-list', [
            'label' => 'Wpisy blogowe',
            'category' => 'blog',
            'fields' => [
                'mode' => [
                    'type' => 'select',
                    'label' => 'Źródło wpisów',
                    'options' => [
                        ['label' => 'Bieżący kontekst', 'value' => 'current'],
                        ['label' => 'Najnowsze wpisy', 'value' => 'latest'],
                    ],
                ],
                'layout' => [
                    'type' => 'select',
                    'label' => 'Układ',
                    'options' => [
                        ['label' => 'Siatka', 'value' => 'grid'],
                        ['label' => 'Lista', 'value' => 'list'],
                        ['label' => 'Karuzela', 'value' => 'carousel'],
                    ],
                ],
                'show_excerpt' => [
                    'type' => 'select',
                    'label' => 'Pokazuj zajawkę',
                    'options' => [
                        ['label' => 'Tak', 'value' => '1'],
                        ['label' => 'Nie', 'value' => '0'],
                    ],
                ],
                'limit' => ['type' => 'number', 'label' => 'Liczba wpisów'],
                'width' => config('cms.blocks.archive-heading.fields.width'),
            ],
            'default_props' => [
                'mode' => 'current',
                'layout' => 'grid',
                'show_excerpt' => '1',
                'limit' => 6,
                'width' => 'contained',
            ],
        ]);

        config()->set('cms.blocks.blog-category-list', [
            'label' => 'Kategorie bloga',
            'category' => 'blog',
            'fields' => [
                'layout' => [
                    'type' => 'select',
                    'label' => 'Układ',
                    'options' => [
                        ['label' => 'Lista', 'value' => 'list'],
                        ['label' => 'Etykiety', 'value' => 'chips'],
                    ],
                ],
                'width' => config('cms.blocks.archive-heading.fields.width'),
            ],
            'default_props' => ['layout' => 'list', 'width' => 'contained'],
        ]);

        config()->set('cms.blocks.blog-post-content', [
            'label' => 'Treść wpisu blogowego',
            'category' => 'blog',
            'fields' => [
                'show_excerpt' => [
                    'type' => 'select',
                    'label' => 'Pokazuj zajawkę',
                    'options' => [
                        ['label' => 'Tak', 'value' => '1'],
                        ['label' => 'Nie', 'value' => '0'],
                    ],
                ],
                'show_featured_image' => [
                    'type' => 'select',
                    'label' => 'Pokazuj obraz wyróżniający',
                    'options' => [
                        ['label' => 'Tak', 'value' => '1'],
                        ['label' => 'Nie', 'value' => '0'],
                    ],
                ],
                'width' => config('cms.blocks.archive-heading.fields.width'),
            ],
            'default_props' => ['show_excerpt' => '1', 'show_featured_image' => '1', 'width' => 'contained'],
        ]);

        $blockData->register('blog-post-content', static function (array $props, string $locale, array $context): array {
            $post = $context['blog_post'] ?? null;

            return ['post' => $post instanceof BlogPostData ? $post : null];
        });
        $blockData->register('blog-category-list', static fn (array $props, string $locale): array => [
            'categories' => $catalog->listCategories($locale),
        ]);
        $blockData->register('blog-post-list', static function (array $props, string $locale, array $context) use ($catalog): array {
            $limit = max(1, min(24, (int) ($props['limit'] ?? 6)));
            $mode = is_string($props['mode'] ?? null) ? $props['mode'] : 'current';
            $post = $context['blog_post'] ?? null;

            if ($mode === 'latest') {
                return ['posts' => $catalog->listLatestPublished($locale, $limit, $post instanceof BlogPostData ? $post->id : null)];
            }

            if ($post instanceof BlogPostData) {
                return ['posts' => $catalog->listRelatedPublished(
                    $post->id,
                    array_map(static fn ($category): int => $category->id, $post->categories),
                    $locale,
                    $limit,
                )];
            }

            $blogPage = is_array($context['blog_page'] ?? null) ? $context['blog_page'] : null;

            return ['posts' => is_array($blogPage['items'] ?? null) ? array_slice($blogPage['items'], 0, $limit) : []];
        });
    }
}
