<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Litecommerce\Modules\Consents\Contracts\MarketingConsentRegistry;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountLookup;
use Litecommerce\Modules\Storefront\Contracts\ThemeRenderer;

/**
 * Renders the login/register forms only — actual authentication stays on
 * the existing JSON endpoints (POST /customer/{register,login,logout} in
 * routes/storefront.php, P1-006), called by the theme's own JS.
 */
final class AccountController extends Controller
{
    public function loginForm(Request $request, string $locale, ThemeRenderer $renderer): View
    {
        return $renderer->render('account.login', ['locale' => $locale]);
    }

    public function registerForm(Request $request, string $locale, ThemeRenderer $renderer): View
    {
        return $renderer->render('account.register', ['locale' => $locale]);
    }

    public function forgotPasswordForm(Request $request, string $locale, ThemeRenderer $renderer): View
    {
        return $renderer->renderWithDefaultFallback('account.forgot-password', ['locale' => $locale]);
    }

    public function resetPasswordForm(Request $request, string $locale, string $token, ThemeRenderer $renderer): View
    {
        return $renderer->renderWithDefaultFallback('account.reset-password', [
            'locale' => $locale,
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function deleteForm(Request $request, string $locale, ThemeRenderer $renderer): View
    {
        return $renderer->renderWithDefaultFallback('account.delete', ['locale' => $locale]);
    }

    public function deleteConfirmForm(Request $request, string $locale, string $token, ThemeRenderer $renderer): View
    {
        return $renderer->renderWithDefaultFallback('account.delete-confirm', ['locale' => $locale, 'token' => $token]);
    }

    public function preferences(Request $request, string $locale, ThemeRenderer $renderer, MarketingConsentRegistry $consents, CustomerAccountLookup $accounts): View
    {
        $email = $accounts->emailFor((int) Auth::guard('customer')->id());

        return $renderer->renderWithDefaultFallback('account.preferences', [
            'locale' => $locale,
            'marketingGranted' => $email !== null && $consents->isGranted($email),
        ]);
    }

    public function unsubscribeForm(Request $request, string $locale, string $token, ThemeRenderer $renderer): View
    {
        return $renderer->renderWithDefaultFallback('account.unsubscribe', ['locale' => $locale, 'token' => $token]);
    }
}
