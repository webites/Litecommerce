<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Contracts\Events;

/**
 * Fired synchronously, in the same request, right after a blog post or
 * category write commits — same DB::afterCommit convention as
 * Themes\Contracts\Events\ThemeActivated. See
 * Catalog\Contracts\Events\CatalogContentChanged's docblock for why this is
 * a coarse signal (Storefront's sitemap cache is the only consumer today),
 * not a per-post diff.
 */
final readonly class BlogContentChanged {}
