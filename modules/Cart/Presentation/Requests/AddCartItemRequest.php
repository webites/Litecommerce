<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
