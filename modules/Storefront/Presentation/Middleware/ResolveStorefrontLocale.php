<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Nieznany prefiks → 404, nie fallback" (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013):
 * a mistyped or disabled language code is a plain 404, never a silent
 * redirect to the default language — a bookmarked/shared URL either keeps
 * working or clearly stops, it does not quietly start showing different
 * content. Also switches the request-lifetime app locale, so validation
 * messages and anything else reading app()->getLocale() match the URL.
 */
final class ResolveStorefrontLocale
{
    public function __construct(private readonly LanguageRepository $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = (string) $request->route('locale');
        $language = collect($this->languages->all())->first(fn ($language): bool => $language->code === $code && $language->isEnabled);

        abort_unless($language !== null, 404);

        App::setLocale($code);

        return $next($request);
    }
}
