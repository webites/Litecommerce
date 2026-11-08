<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Litecommerce\Modules\Catalog\Contracts\AttributeType;

final class SetProductAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'attributes' => ['present', 'array', 'max:100'],
            'attributes.*.attribute_definition_id' => ['required', 'integer', 'distinct', 'exists:attribute_definitions,id'],
            'attributes.*.value_ids' => ['present', 'array', 'max:100'],
            'attributes.*.value_ids.*' => ['integer', 'distinct', 'exists:attribute_values,id'],
            'attributes.*.text_value' => ['nullable', 'string', 'max:10000'],
            'attributes.*.number_value' => ['nullable', 'string', 'regex:/^-?\d{1,14}(\.\d{1,6})?$/'],
            'attributes.*.boolean_value' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $attributes = $this->input('attributes');

            if (! is_array($attributes)) {
                return;
            }

            $definitions = DB::table('attribute_definitions')
                ->whereIn('id', array_map(static fn (mixed $attribute): int => is_array($attribute) ? (int) ($attribute['attribute_definition_id'] ?? 0) : 0, $attributes))
                ->pluck('type', 'id');

            foreach ($attributes as $index => $attribute) {
                if (! is_array($attribute)) {
                    continue;
                }

                $definitionId = (int) ($attribute['attribute_definition_id'] ?? 0);
                $type = $definitions->get($definitionId);
                $valueIds = is_array($attribute['value_ids'] ?? null) ? array_map('intval', $attribute['value_ids']) : [];

                if (! is_string($type)) {
                    continue;
                }

                if ($valueIds !== []) {
                    $matching = DB::table('attribute_values')->where('attribute_definition_id', $definitionId)->whereIn('id', $valueIds)->count();
                    if ($matching !== count(array_unique($valueIds))) {
                        $validator->errors()->add("attributes.{$index}.value_ids", 'Wartość nie należy do wskazanego atrybutu.');
                    }
                }

                $this->validateValueForType($validator, $index, $type, $attribute, $valueIds);
            }
        }];
    }

    /** @param array<string, mixed> $attribute @param list<int> $valueIds */
    private function validateValueForType(Validator $validator, int $index, string $type, array $attribute, array $valueIds): void
    {
        $valid = match ($type) {
            AttributeType::Text->value => is_string($attribute['text_value'] ?? null) && trim($attribute['text_value']) !== '',
            AttributeType::Number->value => is_string($attribute['number_value'] ?? null) && preg_match('/^-?\d{1,14}(\.\d{1,6})?$/', $attribute['number_value']) === 1,
            AttributeType::Boolean->value => array_key_exists('boolean_value', $attribute) && is_bool($attribute['boolean_value']),
            AttributeType::SingleSelect->value, AttributeType::Color->value => count($valueIds) === 1,
            AttributeType::MultiSelect->value => count($valueIds) >= 1,
            default => false,
        };

        if (! $valid) {
            $validator->errors()->add("attributes.{$index}", 'Wartość nie pasuje do typu atrybutu.');
        }
    }
}
