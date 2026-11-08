<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/**
 * Composes a page out of the theme's own named region partials, in a
 * default order the CORE owns (config('storefront.regions'), never the
 * theme) — a theme opts in via the @themeRegions Blade directive instead
 * of hardcoding an @include chain, so a store's own config decides region
 * order/set for every theme, not just one. A theme that wants something
 * the default order can't express is always free to not call the
 * directive and hand-write its own composition instead — this is a
 * convenience default, not a constraint. See
 * docs/knowledge-base/09-presentation.md#domyślny-motyw.
 */
interface ThemeRegionComposer
{
    /**
     * Renders each region configured for $group, in order, as the view
     * "theme::partials.{region}" with $data — e.g. group "catalog.show"
     * renders product-meta/product-details/product-purchase by default,
     * and the sibling group "catalog.show.below" renders product-tabs
     * (description + specification) outside that narrow column. Since
     * 2026-09-17, "theme::" itself falls back to the
     * default theme's own partial when the active theme has none (see
     * Contracts\ThemeRenderer), so a region is only truly skipped (logged,
     * never fatal — same "never break the page" contract as
     * StorefrontSlotRegistry) when even the default theme lacks it, which
     * signals a core misconfiguration rather than an incomplete theme.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $group, array $data = []): string;
}
