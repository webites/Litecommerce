<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Contracts;

interface RedirectResolver
{
    /**
     * $path never carries the {locale} prefix (e.g. "/o-nas", "/blog/wpis"),
     * matching how Storefront/Blog controllers already have $locale resolved
     * separately by ResolveStorefrontLocale before this is ever called.
     * Returns the target path (same shape) to 301 to, or null for "no
     * redirect, let the caller render its normal 404".
     */
    public function resolve(string $locale, string $path): ?string;
}
