<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Illuminate\Http\Request;

interface MenuRenderer
{
    /**
     * $request is taken explicitly (never resolved internally via app('request'))
     * so a singleton implementation can safely memoize per-request without
     * risking a stale Request object under Octane or across multiple
     * $this->get() calls in one test — same reasoning as LanguageAlternateResolver.
     *
     * @return list<MenuNodeViewData> Empty when nothing is assigned/resolved for this location — the nav-menu partial then renders nothing, never an error.
     */
    public function render(Request $request, string $locationId, string $locale): array;
}
