<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;

final readonly class GenerateVariants
{
    public function __construct(private SkuGenerator $skuGenerator) {}

    /**
     * @param  array<int, list<int>>  $selectedValues  definition id => value ids
     */
    public function handle(Product $product, array $selectedValues): int
    {
        ksort($selectedValues);
        $limit = max(1, (int) config('catalog.max_variants_per_product', 100));
        $combinationCount = 1;

        foreach ($selectedValues as $valueIds) {
            if (count($valueIds) === 0 || $combinationCount > intdiv($limit, count($valueIds))) {
                throw ValidationException::withMessages(['options' => "Wybrane opcje tworzą więcej niż {$limit} kombinacji."]);
            }

            $combinationCount *= count($valueIds);
        }

        $combinations = $this->combinations($selectedValues);

        $created = DB::transaction(function () use ($product, $selectedValues, $combinations, $limit): int {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            $optionIds = [];

            foreach ($selectedValues as $definitionId => $valueIds) {
                DB::table('product_options')->updateOrInsert(
                    ['product_id' => $product->getKey(), 'attribute_definition_id' => $definitionId],
                    ['position' => count($optionIds), 'created_at' => now(), 'updated_at' => now()],
                );
                $optionId = (int) DB::table('product_options')
                    ->where('product_id', $product->getKey())
                    ->where('attribute_definition_id', $definitionId)
                    ->value('id');
                $optionIds[$definitionId] = $optionId;

                foreach ($valueIds as $position => $valueId) {
                    DB::table('product_option_values')->insertOrIgnore([
                        'product_option_id' => $optionId,
                        'attribute_value_id' => $valueId,
                        'position' => $position,
                    ]);
                }
            }

            $existingSignatures = ProductVariant::query()->where('product_id', $product->getKey())
                ->whereNotNull('option_signature')->pluck('option_signature')->all();
            $missing = array_values(array_filter($combinations, fn (array $combination): bool => ! in_array($this->signature($combination), $existingSignatures, true)));

            if (count($existingSignatures) + count($missing) > $limit) {
                throw ValidationException::withMessages(['options' => "Produkt nie może mieć więcej niż {$limit} wariantów opcji."]);
            }

            $simpleVariant = $product->variants()->whereNull('option_signature')->where('is_default', true)->first();
            $created = 0;

            foreach ($missing as $combination) {
                $signature = $this->signature($combination);

                if ($simpleVariant !== null) {
                    $variant = $simpleVariant;
                    $variant->update(['option_signature' => $signature]);
                    $simpleVariant = null;
                } else {
                    $variant = ProductVariant::query()->create([
                        'product_id' => $product->getKey(),
                        'sku' => $this->skuGenerator->generate(),
                        'enabled' => true,
                        'is_default' => false,
                        'option_signature' => $signature,
                    ]);
                }

                foreach ($combination as $definitionId => $valueId) {
                    DB::table('variant_option_values')->insert([
                        'product_variant_id' => $variant->getKey(),
                        'product_option_id' => $optionIds[$definitionId],
                        'attribute_value_id' => $valueId,
                    ]);
                }

                $created++;
            }

            return $created;
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return $created;
    }

    /** @param array<int, list<int>> $values @return list<array<int, int>> */
    private function combinations(array $values): array
    {
        $result = [[]];

        foreach ($values as $definitionId => $valueIds) {
            $next = [];
            foreach ($result as $combination) {
                foreach ($valueIds as $valueId) {
                    $next[] = $combination + [$definitionId => $valueId];
                }
            }
            $result = $next;
        }

        return $result;
    }

    /** @param array<int, int> $combination */
    private function signature(array $combination): string
    {
        ksort($combination);

        return hash('sha256', implode('|', array_map(
            static fn (int $definitionId, int $valueId): string => $definitionId.':'.$valueId,
            array_keys($combination),
            array_values($combination),
        )));
    }
}
