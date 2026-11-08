<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Infrastructure;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Litecommerce\Modules\Storefront\Contracts\ThemeNotActiveException;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;
use Litecommerce\Modules\Themes\Contracts\ActiveThemeResolver;

/**
 * `theme-active::` only exists when Themes\Providers\ThemesServiceProvider::boot()
 * found an active theme with a views/ folder, and holds ONLY that theme's own
 * directory (no fallback) — so View::exists() against it already covers both
 * "no active theme" and "active theme lacks this TOP-LEVEL view" without this
 * class needing to ask Themes\Contracts\ActiveThemeResolver itself. The actual
 * render then goes through `theme::`, which additionally falls back to the
 * default theme's own files for partials (see Contracts\ThemeRenderer) — for a
 * view that DOES exist in the active theme (the only way past render()),
 * `theme::` resolves to that same file first. Optional built-in modules can
 * deliberately call renderWithDefaultFallback() to inherit a default page
 * template when an older active theme predates that module.
 */
final class BladeThemeRenderer implements ThemeRenderer
{
    public function __construct(private readonly ActiveThemeResolver $activeTheme) {}

    public function render(string $view, array $data = []): View
    {
        if (! ViewFacade::exists("theme-active::{$view}")) {
            throw new ThemeNotActiveException("Brak aktywnego motywu z widokiem \"{$view}\".");
        }

        return ViewFacade::make("theme::{$view}", $data);
    }

    public function renderWithDefaultFallback(string $view, array $data = []): View
    {
        if ($this->activeTheme->id() === null || ! ViewFacade::exists("theme::{$view}")) {
            throw new ThemeNotActiveException("Brak aktywnego motywu z widokiem \"{$view}\".");
        }

        return ViewFacade::make("theme::{$view}", $data);
    }
}
