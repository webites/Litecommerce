<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Litecommerce\Modules\Identity\Contracts\StaffTwoFactorManager;

final class StaffTwoFactorSettingsController extends Controller
{
    public function store(Request $request, StaffTwoFactorManager $twoFactor): RedirectResponse
    {
        $setup = $twoFactor->beginSetup($this->staffId($request));

        return back()->with([
            'status' => 'Konfiguracja 2FA została rozpoczęta. Potwierdź kod z aplikacji.',
            'two_factor_setup' => [
                'secret' => $setup->secret,
                'otp_auth_uri' => $setup->otpAuthUri,
                'recovery_codes' => $setup->recoveryCodes,
            ],
        ]);
    }

    public function confirm(Request $request, StaffTwoFactorManager $twoFactor): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);

        if (! $twoFactor->confirmSetup($this->staffId($request), $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Podany kod uwierzytelniający jest nieprawidłowy.']);
        }

        return back()->with('status', 'Uwierzytelnianie dwuskładnikowe jest aktywne.');
    }

    public function destroy(Request $request, StaffTwoFactorManager $twoFactor): RedirectResponse
    {
        $twoFactor->disable($this->staffId($request));

        return back()->with('status', 'Uwierzytelnianie dwuskładnikowe zostało wyłączone.');
    }

    private function staffId(Request $request): int
    {
        return (int) $request->user()?->getAuthIdentifier();
    }
}
