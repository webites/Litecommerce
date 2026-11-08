<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a product,
 * category, brand, or catalog_seo_settings write commits (DB::afterCommit,
 * same convention as Themes\Contracts\Events\ThemeActivated /
 * Orders\Contracts\Events\OrderPlaced). A coarse "something sitemap-relevant
 * changed" signal, not a per-field diff — Storefront's own sitemap cache is
 * the only consumer today (Storefront\Providers\StorefrontServiceProvider),
 * clearing every locale's cache rather than computing which one(s) a given
 * write actually touched. A listener that needs more reads it back through
 * Catalog's own public contracts, never through this module's Eloquent models.
 */
final readonly class CatalogContentChanged {}
