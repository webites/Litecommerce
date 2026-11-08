<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every route behind the install lock. Must run before StartSession
 * (registered with $middleware->web(prepend: ...)): until installed.lock
 * exists, the sessions/cache DB tables don't exist yet either, so this
 * switches both to the file driver for the duration of the request.
 *
 * Also bootstraps .env and APP_KEY on the very first request: EncryptCookies
 * needs a key on every request (including install steps 1-3, before the DB
 * even exists), so this can't wait for FinalizeInstallation.
 */
final class EnsureLitecommerceIsInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->runningUnitTests()) {
            if (! file_exists(base_path('.env')) && file_exists(base_path('.env.example'))) {
                @copy(base_path('.env.example'), base_path('.env'));
            }

            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }
        }

        if (! InstallationLock::exists()) {
            config(['session.driver' => 'file', 'cache.default' => 'file']);

            if (! $request->is('install*')) {
                return redirect('/install');
            }
        } elseif ($request->is('install*')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
