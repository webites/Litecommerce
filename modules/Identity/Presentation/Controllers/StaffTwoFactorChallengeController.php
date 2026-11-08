<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;

final class StaffTwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.two_factor_staff_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request, StaffTwoFactorManager $twoFactor): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:64']]);
        $staffId = (int) $request->session()->get('login.two_factor_staff_id');

        if ($staffId < 1) {
            return redirect()->route('login');
        }

        $throttleKey = 'staff-2fa:'.$staffId.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Zbyt wiele prób. Spróbuj ponownie za minutę.',
            ]);
        }

        if (! $twoFactor->verifyChallenge($staffId, trim($validated['code']))) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'code' => 'Podany kod uwierzytelniający jest nieprawidłowy.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('login.two_factor_remember', false);
        $request->session()->forget('login.two_factor_staff_id');
        Auth::guard('web')->loginUsingId($staffId, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
