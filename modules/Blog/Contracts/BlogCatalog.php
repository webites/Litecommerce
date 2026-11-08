<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Contracts;

use Litecommerce\Modules\Cms\Contracts\ArchetypeMatch;

interface BlogCatalog
{
    /** @return array{items: list<BlogPostSummaryData>, total: int} */
    public function paginatePublished(string $locale, ?int $categoryId, int $perPage, int $page): array;

    public function findPublishedPostBySlug(string $slug, string $locale): ?BlogPostData;

    /** @return list<BlogPostSummaryData> */
    public function listLatestPublished(string $locale, int $limit, ?int $excludePostId = null): array;

    /**
     * @param  list<int>  $categoryIds
     * @return list<BlogPostSummaryData>
     */
    public function listRelatedPublished(int $postId, array $categoryIds, string $locale, int $limit): array;

    public function findPostSlugInLocale(int $postId, string $locale): ?string;

    /** @return list<BlogCategoryData> */
    public function listCategories(string $locale): array;

    public function findCategory(int $categoryId, string $locale): ?BlogCategoryData;

    public function findCategorySlugInLocale(int $categoryId, string $locale): ?string;

    public function matchIndexArchive(string $slug, string $locale): ?ArchetypeMatch;

    public function matchCategoryArchive(string $slug, string $locale): ?ArchetypeMatch;

    public function matchPost(string $slug, string $locale): ?ArchetypeMatch;
}
