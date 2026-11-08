<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Litecommerce\Modules\Catalog\Domain\ValidGtin;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;

final class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $variant = $this->route('variant');
        $variantId = $variant instanceof ProductVariant ? $variant->getKey() : null;

        return [
            'locale' => ['required', 'string', 'max:16', 'exists:languages,code'],
            'sku' => ['required', 'string', 'max:96', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('product_variants', 'sku')->ignore($variantId)],
            'gtin' => ['nullable', 'string', new ValidGtin, Rule::unique('product_variants', 'gtin')->ignore($variantId)],
            'mpn' => ['nullable', 'string', 'max:191'],
            'enabled' => ['required', 'boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:5000'],
            'description_html' => ['nullable', 'string', 'max:1000000'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_mm' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'width_mm' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'height_mm' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullableStrings = ['gtin', 'mpn', 'name', 'short_description', 'description_html'];
        $nullableIntegers = ['weight_grams', 'length_mm', 'width_mm', 'height_mm'];
        $normalized = [
            'sku' => strtoupper(trim((string) $this->input('sku'))),
            'enabled' => $this->boolean('enabled'),
        ];

        foreach ($nullableStrings as $field) {
            $normalized[$field] = $this->filled($field) ? trim((string) $this->input($field)) : null;
        }

        foreach ($nullableIntegers as $field) {
            $normalized[$field] = $this->filled($field) ? $this->input($field) : null;
        }

        $this->merge($normalized);
    }
}
