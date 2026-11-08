<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\UiLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

// Panel routes have no /{locale} URL prefix (unlike the storefront), so the staff member's chosen
// UI language lives in the session (mirrored in a plain cookie that survives the install-time session
// switch and logouts) instead — set via PanelLocaleController, read here on every request.
final class ResolvePanelLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get(UiLocale::SESSION_KEY);

        if (! UiLocale::isSupported($locale)) {
            $locale = $request->cookies->get(UiLocale::COOKIE);
        }

        if (UiLocale::isSupported($locale)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
