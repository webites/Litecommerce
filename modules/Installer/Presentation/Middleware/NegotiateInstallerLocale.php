<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Middleware;

use App\Support\UiLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Until the visitor picks a language, the installer follows the browser's preferred supported language. */
final class NegotiateInstallerLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $chosen = $request->session()->get(UiLocale::SESSION_KEY);

        if (! UiLocale::isSupported($chosen) && ! UiLocale::isSupported($request->cookies->get(UiLocale::COOKIE))) {
            $preferred = $request->getPreferredLanguage(UiLocale::supported());

            if (is_string($preferred)) {
                App::setLocale($preferred);
            }
        }

        return $next($request);
    }
}
