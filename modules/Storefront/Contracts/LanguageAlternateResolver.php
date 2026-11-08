<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

use Illuminate\Http\Request;

/**
 * Powers the storefront language switcher partial: given the CURRENTLY
 * viewed page, where should each OTHER enabled language's link point so the
 * visitor stays on the equivalent page (e.g. /pl/strona -> /en/page), not
 * just that language's homepage? User's own spec: "jezeli edytor przelaczy
 * strone na inny jezyk a nie bedzie w bazie instancji tej strony w tym
 * jezyku... na glowna".
 */
interface LanguageAlternateResolver
{
    /** @return array<string, string> locale code => absolute path. A locale missing from this array has no equivalent page — the caller falls back to that locale's homepage ("/{locale}"). */
    public function resolve(Request $request, string $currentLocale): array;
}
