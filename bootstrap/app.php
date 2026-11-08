<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PreventResponseCaching;
use App\Http\Middleware\ResolvePanelLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Litecommerce\Modules\Identity\Presentation\Middleware\EnsureStaffHasPermission;
use Litecommerce\Modules\Installer\Presentation\Middleware\EnsureLitecommerceIsInstalled;
use Litecommerce\Modules\Maintenance\Presentation\Middleware\BlockStorefrontDuringMaintenance;
use Litecommerce\Modules\Scheduler\Presentation\Middleware\RunSchedulerAfterResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['payments/webhooks/*', 'marketing/unsubscribe/*']);

        // Written directly by the storefront's runtime.js (a plain UI
        // preference, not a security-sensitive value) and read back by
        // Storefront\Providers\StorefrontServiceProvider on the very next
        // request — EncryptCookies would otherwise silently discard it,
        // since a client-set plaintext value never decrypts.
        $middleware->encryptCookies(except: ['storefront_theme', 'ui_locale']);

        // ResolveStorefrontLocale (route middleware, storefront pages only) always runs after this and
        // overrides it, so a stray panel_locale session value can never leak into a storefront response.
        $middleware->web(append: [
            ResolvePanelLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RunSchedulerAfterResponse::class,
            BlockStorefrontDuringMaintenance::class,
        ]);

        $middleware->web(prepend: [
            EnsureLitecommerceIsInstalled::class,
        ]);

        // Two independent guards (staff 'web', customer 'customer') need two
        // different guest-redirect targets — a customer hitting their own
        // order history while logged out must land on the storefront's own
        // login page, not the staff panel's, and vice versa.
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->routeIs('storefront.account.*')) {
                return route('storefront.account.login', ['locale' => $request->route('locale')]);
            }

            return route('login');
        });

        $middleware->alias([
            'no-store' => PreventResponseCaching::class,
            'staff.permission' => EnsureStaffHasPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
