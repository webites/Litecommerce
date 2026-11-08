<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;

/**
 * Lazily creates the merchant's editable row for a registered archetype (see
 * ArchetypeRegistry) the first time the panel opens its builder — draft by
 * default, so opening the editor never publishes an override by itself.
 */
final class GetOrCreateArchetypeTemplate
{
    public function handle(string $archetype, string $label, string $locale): CmsPage
    {
        $page = CmsPage::query()->firstOrCreate(
            ['archetype' => $archetype],
            ['status' => PageStatus::Draft->value, 'is_homepage' => false],
        );

        CmsPageTranslation::query()->firstOrCreate(
            ['page_id' => $page->getKey(), 'locale' => $locale],
            [
                // Never resolved by slug (see ArchetypeResolver) and never editable
                // in the builder's own UI (config('cms.archetype_root_fields') has
                // no slug field) — this placeholder only exists to satisfy the
                // (locale, slug) NOT NULL/unique index a real Cms page slug also
                // uses; "@" can never appear in a merchant-typed slug (see
                // SavePageRequest's slug regex), so it can never collide.
                'slug' => "@archetype/{$archetype}",
                'title' => $label,
                'content' => ['content' => [], 'root' => ['props' => []]],
            ],
        );

        return $page;
    }
}
