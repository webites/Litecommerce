<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;

/**
 * Offered to the merchant right after activating a theme (see
 * Themes\Presentation\Controllers\Panel\ThemeController::activate()'s flash
 * prompt) — resets the two kinds of theme-dependent "starting data" this
 * project has today: the homepage's content (ResetHomepageToThemeDefault)
 * and every whole-archetype override (category/brand — deleting the row is
 * exactly DeleteArchetypeTemplate's own effect, just for ALL of them at
 * once via a plain bulk delete, not a per-key loop). A merchant's own
 * one-off Cms pages (any row with archetype IS NULL and is_homepage=false,
 * e.g. "O nas") are never touched — they have no "theme default" to revert to.
 */
final class ResetStorefrontToThemeDefaults
{
    public function __construct(private readonly ResetHomepageToThemeDefault $homepage) {}

    public function handle(string $locale): CmsPage
    {
        return DB::transaction(function () use ($locale): CmsPage {
            CmsPage::query()->whereNotNull('archetype')->delete();

            return $this->homepage->handle($locale);
        });
    }
}
