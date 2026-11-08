<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

final class GenerateVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'options' => ['required', 'array', 'min:1', 'max:6'],
            'options.*.attribute_definition_id' => ['required', 'integer', 'distinct', 'exists:attribute_definitions,id'],
            'options.*.attribute_value_ids' => ['required', 'array', 'min:1', 'max:100'],
            'options.*.attribute_value_ids.*' => ['required', 'integer', 'distinct', 'exists:attribute_values,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $options = $this->input('options');

            if (! is_array($options)) {
                return;
            }

            foreach ($options as $index => $option) {
                if (! is_array($option) || ! isset($option['attribute_definition_id'], $option['attribute_value_ids']) || ! is_array($option['attribute_value_ids'])) {
                    continue;
                }

                $matching = DB::table('attribute_values')
                    ->where('attribute_definition_id', (int) $option['attribute_definition_id'])
                    ->whereIn('id', array_map('intval', $option['attribute_value_ids']))
                    ->count();

                if ($matching !== count(array_unique(array_map('intval', $option['attribute_value_ids'])))) {
                    $validator->errors()->add("options.{$index}.attribute_value_ids", trans('catalog::validation.option_values_attribute'));
                }
            }
        }];
    }
}
