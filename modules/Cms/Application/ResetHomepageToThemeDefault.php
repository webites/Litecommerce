<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;
use Litecommerce\Modules\Cms\Infrastructure\ThemeDefaultContentResolver;

/**
 * "Starting data" for a fresh shop, and a clean slate after switching
 * themes — imports the active theme's own default homepage content
 * (theme::homepage, see ThemeDefaultContentResolver) into the actual
 * is_homepage=true CmsPage row, so it is immediately editable like any
 * other page builder page. Only ever touches content + status; an
 * EXISTING homepage's slug/title/SEO are never regenerated (same
 * never-silently-change-the-URL rule as SlugGenerator elsewhere).
 */
final class ResetHomepageToThemeDefault
{
    public function __construct(private readonly ThemeDefaultContentResolver $defaultContent) {}

    public function handle(string $locale): CmsPage
    {
        return DB::transaction(function () use ($locale): CmsPage {
            $page = CmsPage::query()->where('is_homepage', true)->first();

            if ($page === null) {
                $page = CmsPage::query()->create(['status' => PageStatus::Draft->value, 'is_homepage' => true]);
            }

            $page->update(['status' => PageStatus::Published->value, 'archived_at' => null]);

            $translation = CmsPageTranslation::query()->firstOrNew(['page_id' => $page->getKey(), 'locale' => $locale]);

            if (! $translation->exists) {
                $translation->slug = $this->uniqueSlug($locale);
                $translation->title = 'Strona główna';
            }

            $translation->content = $this->defaultContent->resolve('theme::homepage', ['content' => []]);
            $translation->save();

            return $page->refresh();
        });
    }

    private function uniqueSlug(string $locale): string
    {
        $base = Str::slug('Strona główna');
        $slug = $base;

        for ($suffix = 2; CmsPageTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
