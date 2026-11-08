<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

final class CustomerPasswordResetController extends Controller
{
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::broker('customers')->sendResetLink(['email' => $data['email']]);

        return response()->json(['message' => trans('identity::customer_auth.reset_link_sent')]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $status = Password::broker('customers')->reset($data, function ($customer) use ($data): void {
            $customer->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();
            event(new PasswordReset($customer));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [trans($status)]]);
        }

        return response()->json(['message' => trans('identity::customer_auth.password_reset_success')]);
    }
}
