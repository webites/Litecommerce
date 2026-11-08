<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Application;

use Litecommerce\Modules\Cache\Contracts\ObjectCache;
use Litecommerce\Modules\Storefront\Contracts\StorefrontCacheGroups;

/**
 * Invalidates all sitemap variants after public content changes.
 */
final class FlushSitemapCache
{
    public function __construct(private readonly ObjectCache $cache) {}

    public function handle(): void
    {
        $this->cache->invalidateGroup(StorefrontCacheGroups::SITEMAPS);
    }
}
