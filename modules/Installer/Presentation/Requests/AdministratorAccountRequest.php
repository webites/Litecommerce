<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AdministratorAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (array) trans('installer::messages.attributes');
    }
}
