<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\AttributeType;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;

final class SetProductAttributes
{
    /** @param list<array{attribute_definition_id:int,value_ids:list<int>,text_value:?string,number_value:?string,boolean_value:?bool}> $attributes */
    public function handle(Product $product, array $attributes): void
    {
        DB::transaction(function () use ($product, $attributes): void {
            DB::table('product_attribute_values')->where('product_id', $product->getKey())->delete();
            $types = DB::table('attribute_definitions')
                ->whereIn('id', array_column($attributes, 'attribute_definition_id'))
                ->pluck('type', 'id');

            foreach ($attributes as $position => $attribute) {
                $type = $types->get($attribute['attribute_definition_id']);

                if (in_array($type, [AttributeType::SingleSelect->value, AttributeType::MultiSelect->value, AttributeType::Color->value], true)) {
                    foreach ($attribute['value_ids'] as $valueId) {
                        $this->insert($product, $attribute['attribute_definition_id'], $position, attributeValueId: $valueId);
                    }

                    continue;
                }

                $this->insert(
                    $product,
                    $attribute['attribute_definition_id'],
                    $position,
                    textValue: $attribute['text_value'],
                    numberValue: $attribute['number_value'],
                    booleanValue: $attribute['boolean_value'],
                );
            }
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));
    }

    private function insert(
        Product $product,
        int $definitionId,
        int $position,
        ?int $attributeValueId = null,
        ?string $textValue = null,
        ?string $numberValue = null,
        ?bool $booleanValue = null,
    ): void {
        DB::table('product_attribute_values')->insert([
            'product_id' => $product->getKey(),
            'attribute_definition_id' => $definitionId,
            'attribute_value_id' => $attributeValueId,
            'text_value' => $textValue,
            'number_value' => $numberValue,
            'boolean_value' => $booleanValue,
            'position' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
