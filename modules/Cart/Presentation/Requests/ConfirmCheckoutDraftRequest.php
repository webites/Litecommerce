<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ConfirmCheckoutDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'price_hash' => ['required', 'string', 'size:64'],
            'payment_method_id' => ['sometimes', 'string', 'max:128'],
        ];
    }
}
