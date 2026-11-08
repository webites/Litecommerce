<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Illuminate\Contracts\View\View;

/**
 * The only way Storefront controllers render a page — never `view()`
 * directly, so no controller can accidentally couple itself to a Blade
 * path outside the active theme's own namespace. A named TOP-LEVEL view
 * (e.g. "catalog.show") is a public part of the theme SDK's contract (see
 * "Widoki", docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013)
 * — a theme MUST supply the full documented core set itself. The explicit
 * renderWithDefaultFallback() path is reserved for pages added by optional
 * built-in modules, so older installed themes remain compatible.
 *
 * theme::partials.* included FROM one of those top-level views is a
 * different story (ustalenie 2026-09-17, same doc): a partial missing from
 * the active theme transparently renders the default theme's own copy —
 * see Themes\Providers\ThemesServiceProvider::boot() and
 * Infrastructure\BladeThemeRenderer's docblock for the mechanism.
 */
interface ThemeRenderer
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ThemeNotActiveException no theme is active, or the active
     *                                 theme has no view registered under this name
     */
    public function render(string $view, array $data = []): View;

    /**
     * Render a page owned by an optional built-in module. An active theme may
     * override it, while an older theme can inherit the default-storefront
     * implementation without being reinstalled after the module is added.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ThemeNotActiveException when no theme is active, or neither the
     *                                 active nor default theme supplies the view
     */
    public function renderWithDefaultFallback(string $view, array $data = []): View;
}
