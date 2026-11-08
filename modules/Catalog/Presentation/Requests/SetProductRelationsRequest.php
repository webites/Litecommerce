<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Litecommerce\Modules\Catalog\Contracts\ProductRelationType;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;

final class SetProductRelationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'relations' => ['present', 'array', 'max:200'],
            'relations.*.type' => ['required', Rule::enum(ProductRelationType::class)],
            'relations.*.target_product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $relations = $this->input('relations');

            if (! is_array($relations)) {
                return;
            }

            $product = $this->route('product');
            $sourceId = $product instanceof Product ? (int) $product->getKey() : null;
            $seen = [];

            foreach ($relations as $index => $relation) {
                if (! is_array($relation) || ! isset($relation['type'], $relation['target_product_id'])) {
                    continue;
                }

                $targetId = (int) $relation['target_product_id'];

                if ($sourceId !== null && $targetId === $sourceId) {
                    $validator->errors()->add("relations.{$index}.target_product_id", 'Produkt nie może być powiązany sam ze sobą.');
                }

                $key = $relation['type'].'-'.$targetId;

                if (isset($seen[$key])) {
                    $validator->errors()->add("relations.{$index}.target_product_id", 'Ten produkt jest już dodany dla wybranego typu powiązania.');
                }

                $seen[$key] = true;
            }
        }];
    }
}
