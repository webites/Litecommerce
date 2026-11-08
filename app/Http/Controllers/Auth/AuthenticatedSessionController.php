<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Identity\Contracts\StaffCredentialVerifier;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        StaffCredentialVerifier $credentials,
        StaffTwoFactorManager $twoFactor,
    ): RedirectResponse {
        $staff = $request->authenticate($credentials);
        $staffId = (int) $staff->getAuthIdentifier();

        if ($twoFactor->isEnabled($staffId)) {
            $request->session()->put([
                'login.two_factor_staff_id' => $staffId,
                'login.two_factor_remember' => $request->boolean('remember'),
            ]);
            $request->session()->regenerateToken();

            return redirect()->route('two-factor.challenge');
        }

        Auth::guard('web')->login($staff, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
