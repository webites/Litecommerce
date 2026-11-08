<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Maintenance\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceState;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers every storefront request with a theme-independent 503 page while maintenance is on. The panel,
 * staff sign-in and payment webhooks stay reachable; staff may browse the storefront read-only but never
 * reach the cart, checkout or customer endpoints.
 */
final class BlockStorefrontDuringMaintenance
{
    private const ALWAYS_REACHABLE = [
        'panel', 'panel/*', 'login', 'logout', 'forgot-password', 'reset-password/*', 'password/*',
        'confirm-password', 'two-factor-challenge', 'profile', 'up', 'install', 'install/*',
        'payments/webhooks/*', 'storage/*', 'sanctum/*',
    ];

    private const PURCHASE_PATHS = ['cart', 'cart/*', 'checkout', 'checkout/*', 'customer/*', '*/cart', '*/checkout', '*/checkout/*'];

    public function __construct(private readonly MaintenanceMode $maintenance, private readonly StaffPermissionChecker $permissions, private readonly LanguageRepository $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $this->maintenance->state();

        if ($state === null || $request->is(self::ALWAYS_REACHABLE) || $this->isStaffPreview($request)) {
            return $next($request);
        }

        return $this->unavailable($request, $state);
    }

    private function isStaffPreview(Request $request): bool
    {
        if (! $request->isMethodSafe() || $request->is(self::PURCHASE_PATHS)) {
            return false;
        }

        try {
            $staffId = Auth::guard('web')->id();

            return is_int($staffId) && $this->permissions->allows($staffId, CorePermission::AccessPanel->value);
        } catch (\Throwable) {
            return false;
        }
    }

    /** The database may be mid-migration, so any failure falls back to the application locale. */
    private function storeLocale(): string
    {
        try {
            $default = $this->languages->default();

            return $default === null ? (string) config('app.fallback_locale', 'en') : $default->code;
        } catch (\Throwable) {
            return (string) config('app.fallback_locale', 'en');
        }
    }

    private function unavailable(Request $request, MaintenanceState $state): Response
    {
        $locale = $request->headers->has('Accept-Language') ? (string) $request->getPreferredLanguage(['pl', 'en']) : $this->storeLocale();
        $headers = ['Retry-After' => (string) $state->retryAfterSeconds, 'Cache-Control' => 'no-store, private'];
        $title = trans('maintenance::page.title', [], $locale);
        $message = $state->message ?? trans('maintenance::page.default_message', [], $locale);

        if ($request->expectsJson()) {
            return response()->json(['code' => 'maintenance', 'message' => $message], 503, $headers);
        }

        return response()->view('maintenance::unavailable', [
            'locale' => $locale, 'title' => $title, 'message' => $message,
            'hint' => trans('maintenance::page.hint', [], $locale),
        ], 503, $headers);
    }
}
