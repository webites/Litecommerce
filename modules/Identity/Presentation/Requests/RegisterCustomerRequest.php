<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:customer_accounts,email'],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'marketing_consent' => ['sometimes', 'boolean'],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }
}
