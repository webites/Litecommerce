<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Litecommerce\Modules\Identity\Contracts\AccountDeletionRefusedException;
use Litecommerce\Modules\Identity\Contracts\CustomerAccountDeletion;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class CustomerAccountDeletionController extends Controller
{
    private const MAX_PASSWORD_ATTEMPTS = 5;

    public function __construct(private readonly LanguageRepository $languages) {}

    public function request(Request $request, CustomerAccountDeletion $deletion): JsonResponse
    {
        $this->useRequestedLocale($request);
        $data = $request->validate(['password' => ['required', 'string'], 'locale' => ['nullable', 'string', 'max:10']]);
        $customerId = (int) Auth::guard('customer')->id();
        $throttleKey = 'account-deletion:'.$customerId.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PASSWORD_ATTEMPTS)) {
            throw ValidationException::withMessages(['password' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey), 'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60)])]);
        }

        try {
            $deletion->request($customerId, $data['password']);
        } catch (AccountDeletionRefusedException $exception) {
            if ($exception->reason === AccountDeletionRefusedException::INVALID_PASSWORD) {
                RateLimiter::hit($throttleKey);
            }

            throw ValidationException::withMessages(['password' => $exception->getMessage()]);
        }

        RateLimiter::clear($throttleKey);

        return response()->json(['message' => trans('identity::account_deletion.link_sent')]);
    }

    public function confirm(Request $request, CustomerAccountDeletion $deletion): JsonResponse
    {
        $this->useRequestedLocale($request);
        $data = $request->validate(['token' => ['required', 'string', 'max:128'], 'locale' => ['nullable', 'string', 'max:10']]);

        try {
            $deletedId = $deletion->confirm($data['token']);
        } catch (AccountDeletionRefusedException $exception) {
            throw ValidationException::withMessages(['token' => $exception->getMessage()]);
        }

        if ((int) Auth::guard('customer')->id() === $deletedId) {
            Auth::guard('customer')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => trans('identity::account_deletion.deleted')]);
    }

    private function useRequestedLocale(Request $request): void
    {
        $code = (string) $request->input('locale');

        if (collect($this->languages->all())->contains(fn ($language): bool => $language->code === $code && $language->isEnabled)) {
            App::setLocale($code);
        }
    }
}
