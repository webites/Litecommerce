<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Litecommerce\Modules\Blog\Contracts\BlogCatalog;
use Litecommerce\Modules\Blog\Contracts\BlogCategoryData;
use Litecommerce\Modules\Blog\Contracts\BlogPostData;
use Litecommerce\Modules\Blog\Contracts\BlogPostSummaryData;
use Litecommerce\Modules\Blog\Contracts\BlogStatus;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategory;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategoryTranslation;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPost;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPostTranslation;
use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;
use Litecommerce\Modules\Media\Contracts\MediaData;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;

final class EloquentBlogCatalog implements BlogCatalog
{
    public function __construct(private readonly MediaLibrary $media) {}

    public function paginatePublished(string $locale, ?int $categoryId, int $perPage, int $page): array
    {
        $query = BlogPostTranslation::query()
            ->where('locale', $locale)
            ->whereHas('post', fn (Builder $query) => $this->publishedPosts($query))
            ->with(['post.categories.translations' => fn ($query) => $query->where('locale', $locale)]);

        if ($categoryId !== null) {
            $query->whereHas('post.categories', fn (Builder $query) => $query->whereKey($categoryId)->whereNull('archived_at'));
        }

        $paginator = $query
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.post_id')
            ->orderByDesc('blog_posts.publish_at')
            ->orderByDesc('blog_posts.id')
            ->select('blog_post_translations.*')
            ->paginate($perPage, ['*'], 'page', $page);

        $translations = $paginator->items();
        $images = $this->featuredImages($translations);

        return [
            'items' => array_map(fn (BlogPostTranslation $translation): BlogPostSummaryData => $this->toSummary($translation, $images), $translations),
            'total' => $paginator->total(),
        ];
    }

    public function findPublishedPostBySlug(string $slug, string $locale): ?BlogPostData
    {
        $translation = BlogPostTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('post', fn (Builder $query) => $this->publishedPosts($query))
            ->with(['post.categories.translations' => fn ($query) => $query->where('locale', $locale)])
            ->first();

        if ($translation === null) {
            return null;
        }

        $featuredImage = $this->featuredImage($translation->post);

        return new BlogPostData(
            id: (int) $translation->post_id,
            slug: $translation->slug,
            title: $translation->title,
            excerpt: $translation->excerpt,
            contentHtml: $translation->content_html,
            seoTitle: $translation->seo_title,
            seoDescription: $translation->seo_description,
            publishedAt: $translation->post?->publish_at?->toAtomString(),
            featuredImageUrl: $featuredImage?->url,
            categories: $this->categoriesForPost($translation->post),
            featuredImageSrcset: $featuredImage?->srcset(),
            featuredImageWidth: $featuredImage?->width,
            featuredImageHeight: $featuredImage?->height,
        );
    }

    public function listLatestPublished(string $locale, int $limit, ?int $excludePostId = null): array
    {
        $query = BlogPostTranslation::query()
            ->where('locale', $locale)
            ->whereHas('post', fn (Builder $query) => $this->publishedPosts($query))
            ->with(['post.categories.translations' => fn ($query) => $query->where('locale', $locale)]);

        if ($excludePostId !== null) {
            $query->where('post_id', '!=', $excludePostId);
        }

        $translations = $query
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.post_id')
            ->orderByDesc('blog_posts.publish_at')
            ->orderByDesc('blog_posts.id')
            ->select('blog_post_translations.*')
            ->limit(max(1, $limit))
            ->get()
            ->all();

        return $this->summaries($translations);
    }

    public function listRelatedPublished(int $postId, array $categoryIds, string $locale, int $limit): array
    {
        if ($categoryIds === []) {
            return $this->listLatestPublished($locale, $limit, $postId);
        }

        $translations = BlogPostTranslation::query()
            ->where('locale', $locale)
            ->where('post_id', '!=', $postId)
            ->whereHas('post', fn (Builder $query) => $this->publishedPosts($query))
            ->whereHas('post.categories', fn (Builder $query) => $query->whereIn('blog_categories.id', $categoryIds)->whereNull('archived_at'))
            ->with(['post.categories.translations' => fn ($query) => $query->where('locale', $locale)])
            ->join('blog_posts', 'blog_posts.id', '=', 'blog_post_translations.post_id')
            ->orderByDesc('blog_posts.publish_at')
            ->orderByDesc('blog_posts.id')
            ->select('blog_post_translations.*')
            ->limit(max(1, $limit))
            ->get()
            ->all();

        return $this->summaries($translations);
    }

    public function findPostSlugInLocale(int $postId, string $locale): ?string
    {
        return BlogPostTranslation::query()
            ->where('post_id', $postId)
            ->where('locale', $locale)
            ->whereHas('post', fn (Builder $query) => $this->publishedPosts($query))
            ->value('slug');
    }

    public function listCategories(string $locale): array
    {
        return BlogCategoryTranslation::query()
            ->where('locale', $locale)
            ->whereHas('category', fn (Builder $query) => $query->whereNull('archived_at'))
            ->orderBy('name')
            ->get()
            ->map(fn (BlogCategoryTranslation $translation): BlogCategoryData => $this->toCategory($translation))
            ->all();
    }

    public function findCategory(int $categoryId, string $locale): ?BlogCategoryData
    {
        $translation = BlogCategoryTranslation::query()
            ->where('category_id', $categoryId)
            ->where('locale', $locale)
            ->whereHas('category', fn (Builder $query) => $query->whereNull('archived_at'))
            ->first();

        return $translation === null ? null : $this->toCategory($translation);
    }

    public function findCategorySlugInLocale(int $categoryId, string $locale): ?string
    {
        return BlogCategoryTranslation::query()
            ->where('category_id', $categoryId)
            ->where('locale', $locale)
            ->whereHas('category', fn (Builder $query) => $query->whereNull('archived_at'))
            ->value('slug');
    }

    public function matchIndexArchive(string $slug, string $locale): ?ArchetypeMatch
    {
        if ($slug !== 'blog') {
            return null;
        }

        $title = trans('blog::messages.title', [], $locale);

        return new ArchetypeMatch(
            title: $title,
            description: null,
            blockContext: ['blog_archive' => true],
            tags: ['blog_title' => $title],
        );
    }

    public function matchCategoryArchive(string $slug, string $locale): ?ArchetypeMatch
    {
        $translation = BlogCategoryTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('category', fn (Builder $query) => $query->whereNull('archived_at'))
            ->first();

        if ($translation === null) {
            return null;
        }

        return new ArchetypeMatch(
            title: $translation->name,
            description: $translation->description,
            blockContext: [
                'blog_category_id' => (int) $translation->category_id,
                'seo_title' => $translation->seo_title,
                'seo_description' => $translation->seo_description,
            ],
            tags: [
                'blog_category_name' => $translation->name,
                'blog_category_description' => $translation->description ?? '',
            ],
        );
    }

    public function matchPost(string $slug, string $locale): ?ArchetypeMatch
    {
        $post = $this->findPublishedPostBySlug($slug, $locale);

        if ($post === null) {
            return null;
        }

        return new ArchetypeMatch(
            title: $post->title,
            description: $post->excerpt,
            blockContext: [
                'blog_post' => $post,
                'blog_post_id' => $post->id,
                'blog_category_ids' => array_map(static fn (BlogCategoryData $category): int => $category->id, $post->categories),
                'seo_title' => $post->seoTitle,
                'seo_description' => $post->seoDescription,
            ],
            tags: [
                'blog_post_title' => $post->title,
                'blog_post_excerpt' => $post->excerpt ?? '',
            ],
        );
    }

    private function publishedPosts(Builder $query): void
    {
        $query
            ->where('status', BlogStatus::Published->value)
            ->whereNull('archived_at')
            ->where(fn (Builder $query) => $query->whereNull('publish_at')->orWhere('publish_at', '<=', Date::now()));
    }

    /** @param array<int, MediaData> $images */
    private function toSummary(BlogPostTranslation $translation, array $images): BlogPostSummaryData
    {
        $featuredMediaId = $translation->post?->featured_media_id;
        $featuredImage = $featuredMediaId === null ? null : ($images[$featuredMediaId] ?? null);

        return new BlogPostSummaryData(
            id: (int) $translation->post_id,
            slug: $translation->slug,
            title: $translation->title,
            excerpt: $translation->excerpt,
            publishedAt: $translation->post?->publish_at?->toAtomString(),
            featuredImageUrl: $featuredImage?->url,
            categories: $this->categoriesForPost($translation->post),
            featuredImageSrcset: $featuredImage?->srcset(),
            featuredImageWidth: $featuredImage?->width,
            featuredImageHeight: $featuredImage?->height,
        );
    }

    /**
     * @param  list<BlogPostTranslation>  $translations
     * @return array<int, MediaData>
     */
    private function featuredImages(array $translations): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (BlogPostTranslation $translation): ?int => $translation->post?->featured_media_id,
            $translations,
        ))));

        return $this->media->findMany($ids);
    }

    /**
     * @param  list<BlogPostTranslation>  $translations
     * @return list<BlogPostSummaryData>
     */
    private function summaries(array $translations): array
    {
        $images = $this->featuredImages($translations);

        return array_map(fn (BlogPostTranslation $translation): BlogPostSummaryData => $this->toSummary($translation, $images), $translations);
    }

    private function featuredImage(?BlogPost $post): ?MediaData
    {
        return $post?->featured_media_id === null ? null : $this->media->find($post->featured_media_id);
    }

    /** @return list<BlogCategoryData> */
    private function categoriesForPost(?BlogPost $post): array
    {
        if ($post === null) {
            return [];
        }

        return $post->categories
            ->filter(static fn (BlogCategory $category): bool => $category->archived_at === null && $category->translations->isNotEmpty())
            ->map(fn (BlogCategory $category): BlogCategoryData => $this->toCategory($category->translations->first()))
            ->values()
            ->all();
    }

    private function toCategory(BlogCategoryTranslation $translation): BlogCategoryData
    {
        return new BlogCategoryData(
            id: (int) $translation->category_id,
            slug: $translation->slug,
            name: $translation->name,
            description: $translation->description,
            seoTitle: $translation->seo_title,
            seoDescription: $translation->seo_description,
        );
    }
}
