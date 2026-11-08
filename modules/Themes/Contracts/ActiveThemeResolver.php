<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

/**
 * What Storefront needs to render a page without knowing which theme is
 * active or how its assets are published — see "Moduł Themes"
 * (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 */
interface ActiveThemeResolver
{
    public function id(): ?string;

    /** Public URL for a path inside the active theme's assets/ folder, or null when no theme is active. */
    public function assetUrl(string $relativePath): ?string;

    /**
     * Whether the active theme's manifest declares "supports_dark_mode":
     * true — i.e. it actually ships [data-theme="dark"] CSS, not merely
     * that a visitor could set the attribute. Storefront reads this to
     * decide whether to offer the mode toggle at all; a theme without
     * matching styles should never show a control with no visible effect.
     * False (never an exception) when no theme is active or the manifest
     * omits/misuses the field — the same lenient-default convention as
     * every other optional manifest field.
     */
    public function supportsDarkMode(): bool;

    /**
     * Every location the active theme's manifest declares ("menu_locations")
     * — the theme-side half of "a theme registers menu locations" (themes
     * have no PHP in this codebase, so this is a manifest field, not a
     * runtime registry call like Storefront\Contracts\MenuLocationRegistry,
     * which is the plugin-side equivalent). Empty (never an exception) when
     * no theme is active or the field is missing/malformed — same lenient
     * convention as supportsDarkMode() above.
     *
     * @return list<array{id: string, label: string}>
     */
    public function menuLocations(): array;
}
