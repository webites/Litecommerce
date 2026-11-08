<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @method array{host:string,port:string,database:string,username:string,password:?string} validated($key = null, $default = null)
 */
final class DatabaseConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'string', 'max:5'],
            'database' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'max:64'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return (array) trans('installer::messages.attributes');
    }
}
