<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AttachCouponCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'pin' => ['nullable', 'string', 'max:32'],
        ];
    }
}
