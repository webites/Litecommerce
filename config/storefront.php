<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Theme page regions
    |--------------------------------------------------------------------------
    |
    | For each page group, the ordered list of "region" partials a theme's
    | own view renders through @themeRegions('group', [...]) instead of
    | hardcoding every @include and their order itself — see
    | Storefront\Contracts\ThemeRegionComposer and
    | docs/knowledge-base/09-presentation.md#domyślny-motyw.
    |
    | This is store-level configuration, not theme-owned (a theme package
    | has no PHP/config of its own — see 09-presentation.md), so it is the
    | one place a developer running THIS store can reorder, drop, or add a
    | region without touching any theme file. A theme is always free to
    | skip @themeRegions entirely and hand-write its own @include chain
    | instead, in which case this config has no effect on it.
    |
    | Each region resolves to the view "theme::partials.{region}" — the
    | active theme must provide a matching partial for every region it
    | wants rendered.
    |
    */

    'regions' => [
        'catalog.show' => ['product-meta', 'product-details', 'product-purchase'],
        // Rendered OUTSIDE .product-info, full width below the two-column
        // gallery+purchase layout — the long description reads poorly
        // squeezed into that narrow column. See
        // docs/knowledge-base/09-presentation.md#domyślny-motyw.
        'catalog.show.below' => ['product-tabs'],
    ],

];
