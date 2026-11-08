<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetCartCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'size:3'],
        ];
    }
}
