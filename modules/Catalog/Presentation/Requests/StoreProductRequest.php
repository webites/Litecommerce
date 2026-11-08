<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Litecommerce\Modules\Catalog\Contracts\ProductStatus;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Catalog\Contracts\ProductUnit;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;
use Litecommerce\Modules\Catalog\Domain\ValidGtin;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $quantity = ['required', 'string', 'regex:/^\d{1,14}(\.\d{1,6})?$/'];

        return [
            'locale' => ['required', 'string', 'max:16', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:5000'],
            'description_html' => ['nullable', 'string', 'max:1000000'],
            'type' => ['required', Rule::in(array_map(static fn ($type): string => $type->key, app(ProductTypeRegistry::class)->all()))],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'variant_seo_mode' => ['nullable', Rule::enum(VariantSeoMode::class)],
            'featured' => ['required', 'boolean'],
            'unit_code' => ['required', Rule::enum(ProductUnit::class)],
            'minimum_quantity' => $quantity,
            'maximum_quantity' => ['nullable', 'string', 'regex:/^\d{1,14}(\.\d{1,6})?$/'],
            'quantity_step' => $quantity,
            'sku' => ['nullable', 'string', 'max:96', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', 'unique:product_variants,sku'],
            'gtin' => ['nullable', 'string', new ValidGtin, 'unique:product_variants,gtin'],
            'mpn' => ['nullable', 'string', 'max:191'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'category_ids' => ['present', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'primary_category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->filled('sku') ? strtoupper(trim((string) $this->input('sku'))) : null,
            'gtin' => $this->filled('gtin') ? trim((string) $this->input('gtin')) : null,
            'mpn' => $this->filled('mpn') ? trim((string) $this->input('mpn')) : null,
            'featured' => $this->boolean('featured'),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $minimum = (string) $this->input('minimum_quantity');
            $maximum = $this->input('maximum_quantity');
            $step = (string) $this->input('quantity_step');

            if (is_numeric($minimum) && bccomp($minimum, '0', 6) <= 0) {
                $validator->errors()->add('minimum_quantity', trans('catalog::validation.minimum_quantity'));
            }

            if (is_numeric($step) && bccomp($step, '0', 6) <= 0) {
                $validator->errors()->add('quantity_step', trans('catalog::validation.quantity_step'));
            }

            if (is_string($maximum) && is_numeric($maximum) && is_numeric($minimum) && bccomp($maximum, $minimum, 6) < 0) {
                $validator->errors()->add('maximum_quantity', trans('catalog::validation.maximum_quantity'));
            }

            if (app(ProductTypeRegistry::class)->get((string) $this->input('type'))->soldAsSingleUnit
                && ($this->input('unit_code') !== ProductUnit::Piece->value
                    || bccomp($minimum, '1', 6) !== 0
                    || $maximum !== '1'
                    || bccomp($step, '1', 6) !== 0)) {
                $validator->errors()->add('type', trans('catalog::validation.single_unit_type'));
            }

            $primaryCategoryId = $this->input('primary_category_id');
            $categoryIds = $this->input('category_ids', []);

            if ($primaryCategoryId !== null
                && is_array($categoryIds)
                && ! in_array((int) $primaryCategoryId, array_map('intval', $categoryIds), true)) {
                $validator->errors()->add('primary_category_id', trans('catalog::validation.primary_category'));
            }
        }];
    }
}
