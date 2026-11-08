<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure\Repositories;

use Litecommerce\Modules\Cms\Contracts\CmsPageData;
use Litecommerce\Modules\Cms\Contracts\CmsPageRepository;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;

final class EloquentCmsPageRepository implements CmsPageRepository
{
    public function findPublishedBySlug(string $slug, string $locale): ?CmsPageData
    {
        $translation = CmsPageTranslation::query()
            ->where('locale', $locale)
            ->where('slug', $slug)
            ->whereHas('page', fn ($query) => $query->where('status', PageStatus::Published->value))
            ->first();

        return $translation === null ? null : $this->toData($translation);
    }

    public function findPublishedHomepage(string $locale): ?CmsPageData
    {
        $page = CmsPage::query()
            ->where('status', PageStatus::Published->value)
            ->where('is_homepage', true)
            ->first();

        if ($page === null) {
            return null;
        }

        $translation = $page->translations()->where('locale', $locale)->first();

        return $translation === null ? null : $this->toData($translation);
    }

    public function findPublishedArchetype(string $archetype, string $locale): ?CmsPageData
    {
        $page = CmsPage::query()
            ->where('status', PageStatus::Published->value)
            ->where('archetype', $archetype)
            ->first();

        if ($page === null) {
            return null;
        }

        $translation = $page->translations()->where('locale', $locale)->first();

        return $translation === null ? null : $this->toData($translation);
    }

    public function findPublishedSlugInLocale(int $pageId, string $locale): ?string
    {
        return CmsPageTranslation::query()
            ->where('page_id', $pageId)
            ->where('locale', $locale)
            ->whereHas('page', fn ($query) => $query->where('status', PageStatus::Published->value))
            ->value('slug');
    }

    public function findPublishedTitleAndSlug(int $pageId, string $locale): ?array
    {
        $translation = CmsPageTranslation::query()
            ->where('page_id', $pageId)
            ->where('locale', $locale)
            ->whereHas('page', fn ($query) => $query->where('status', PageStatus::Published->value))
            ->first();

        return $translation === null ? null : ['title' => $translation->title, 'slug' => $translation->slug];
    }

    public function listAllPublishedSlugs(string $locale): array
    {
        return CmsPageTranslation::query()
            ->where('locale', $locale)
            ->whereHas('page', fn ($query) => $query->where('status', PageStatus::Published->value)
                ->where('is_homepage', false)
                ->whereNull('archetype'))
            ->pluck('slug')
            ->all();
    }

    private function toData(CmsPageTranslation $translation): CmsPageData
    {
        return new CmsPageData(
            id: (int) $translation->page_id,
            slug: $translation->slug,
            title: $translation->title,
            seoTitle: $translation->seo_title,
            seoDescription: $translation->seo_description,
            content: $translation->content,
        );
    }
}
