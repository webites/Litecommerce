<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;

/**
 * UpdatePage's counterpart for an archetype template row — no slug/SEO, since
 * the same row applies to many categories/brands, each resolved at render
 * time (see ArchetypeResolver). Unlike a Cms page, saving here always
 * publishes immediately (user decision, 2026-09-19: a merchant expects the
 * builder's save action to go live right away, not sit as a draft) — going
 * back to the theme default is the separate DeleteArchetypeTemplate action,
 * not an 'archived' status.
 */
final class UpdateArchetypeTemplate
{
    public function handle(CmsPage $page, UpdateArchetypeTemplateData $data): CmsPage
    {
        return DB::transaction(function () use ($page, $data): CmsPage {
            $page->update([
                'status' => PageStatus::Published->value,
                'publish_at' => $page->publish_at ?? now(),
                'archived_at' => null,
            ]);

            $translation = CmsPageTranslation::query()->firstOrNew(['page_id' => $page->getKey(), 'locale' => $data->locale]);
            $translation->title = $data->title;

            if (! $translation->exists) {
                // Never resolved by slug (see ArchetypeResolver) — this placeholder
                // only satisfies the same NOT NULL/unique index a real page slug
                // uses, one per (archetype, locale). Mirrors GetOrCreateArchetypeTemplate's
                // slug for the row's first (default-locale) translation.
                $translation->slug = "@archetype/{$page->archetype}/{$data->locale}";
                $translation->content = ['content' => [], 'root' => ['props' => []]];
            }

            $translation->save();

            return $page->refresh();
        });
    }
}
