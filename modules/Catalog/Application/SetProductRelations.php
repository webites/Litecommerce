<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductRelation;

/**
 * Upsell/cross-sell links are one-directional and manually curated —
 * see docs/knowledge-base/03-catalog.md. Each call replaces the full set
 * for the product, same "delete then insert" shape as SetProductAttributes.
 */
final class SetProductRelations
{
    /** @param list<array{type:string,target_product_id:int}> $relations */
    public function handle(Product $product, array $relations): void
    {
        DB::transaction(function () use ($product, $relations): void {
            ProductRelation::query()->where('source_product_id', $product->getKey())->delete();
            $positions = [];

            foreach ($relations as $relation) {
                $type = $relation['type'];
                $positions[$type] = ($positions[$type] ?? -1) + 1;

                ProductRelation::query()->create([
                    'source_product_id' => $product->getKey(),
                    'type' => $type,
                    'target_product_id' => $relation['target_product_id'],
                    'position' => $positions[$type],
                ]);
            }
        });
    }
}
