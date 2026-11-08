<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Application;

use Litecommerce\Modules\Themes\Contracts\ThemeStatus;
use Litecommerce\Modules\Themes\Infrastructure\Models\Theme;

/**
 * Leaves the storefront without an active theme — there is no second,
 * fully-built theme to fall back to today (see "Zakres i świadome
 * cięcia", 09-presentation.md#projekt-techniczny-p1-013), so
 * Storefront\Contracts\ThemeRenderer responds with a plain 503 until
 * another theme is activated. Published assets are left in place
 * (removed only on delete) — cheap to leave, and re-activating the same
 * theme later needs no republish.
 */
final class DeactivateTheme
{
    public function execute(string $themeId): void
    {
        $theme = Theme::query()->findOrFail($themeId);

        if ($theme->status === ThemeStatus::Installed->value) {
            return;
        }

        $theme->update(['status' => ThemeStatus::Installed->value, 'activated_at' => null]);
    }
}
