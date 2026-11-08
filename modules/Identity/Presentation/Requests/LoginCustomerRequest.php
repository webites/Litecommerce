<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;

final class LoginCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /** @throws ValidationException */
    public function authenticate(): CustomerAccount
    {
        $this->ensureIsNotRateLimited();

        $customer = CustomerAccount::query()->where('email', $this->string('email')->toString())->first();

        if ($customer === null || ! Hash::check($this->string('password')->toString(), $customer->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        return $customer;
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')->toString()).'|'.$this->ip());
    }
}
