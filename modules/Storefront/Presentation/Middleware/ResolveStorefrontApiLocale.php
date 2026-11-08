<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * The storefront JSON endpoints have no /{locale} prefix, so the page's
 * language travels in the X-Storefront-Locale header set by runtime.js
 * or in a plain `locale` form field. An unknown or disabled code falls
 * back to the default language.
 */
final class ResolveStorefrontApiLocale
{
    public const HEADER = 'X-Storefront-Locale';

    public function __construct(private readonly LanguageRepository $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requested = (string) ($request->header(self::HEADER) ?: $request->input('locale'));

        $match = collect($this->languages->all())
            ->first(static fn ($language): bool => $language->isEnabled && $language->code === $requested)
            ?? $this->languages->default();

        if ($match !== null) {
            App::setLocale($match->code);
        }

        return $next($request);
    }
}
