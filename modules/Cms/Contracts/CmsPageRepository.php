<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

/** The only way outside Cms (Storefront's page-rendering controllers) reads page content — never the Eloquent models directly. Both methods only ever return published pages. */
interface CmsPageRepository
{
    public function findPublishedBySlug(string $slug, string $locale): ?CmsPageData;

    public function findPublishedHomepage(string $locale): ?CmsPageData;

    /** The merchant's published whole-archetype override (see ArchetypeRegistry), if any — null means "use the theme's default template for this archetype". */
    public function findPublishedArchetype(string $archetype, string $locale): ?CmsPageData;

    /** The SAME page's own slug in another locale (e.g. for a storefront language switcher that stays on the equivalent page) — null when no translation exists in that locale. $pageId comes from an already-resolved CmsPageData::$id. */
    public function findPublishedSlugInLocale(int $pageId, string $locale): ?string;

    /** @return ?array{title: string, slug: string} Null when the page doesn't exist, isn't published, or has no translation in $locale — e.g. resolving a menu item that points at a page id. */
    public function findPublishedTitleAndSlug(int $pageId, string $locale): ?array;

    /**
     * Every published, ordinary page's slug in $locale — for the sitemap
     * builder ONLY. Excludes the homepage (sitemap lists it as the locale
     * root, not its own slug) and archetype template rows (their slug is a
     * synthetic "@archetype/{key}" placeholder, never a real visitable URL —
     * see ArchetypeResolver, which resolves category/brand/blog pages by
     * THEIR OWN slug instead).
     *
     * @return list<string>
     */
    public function listAllPublishedSlugs(string $locale): array;
}
