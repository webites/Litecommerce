<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a CMS page (or
 * archetype template) write commits — same DB::afterCommit convention as
 * Themes\Contracts\Events\ThemeActivated. See
 * Catalog\Contracts\Events\CatalogContentChanged's docblock for why this is
 * a coarse signal (Storefront's sitemap cache is the only consumer today),
 * not a per-page diff.
 */
final readonly class CmsContentChanged {}
