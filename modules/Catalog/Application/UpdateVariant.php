<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Litecommerce\Modules\Catalog\Infrastructure\Models\VariantTranslation;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;

final class UpdateVariant
{
    public function __construct(private readonly HtmlContentFilter $htmlFilter) {}

    public function handle(ProductVariant $variant, UpdateVariantData $data): ProductVariant
    {
        $updated = DB::transaction(function () use ($variant, $data): ProductVariant {
            $variant->update([
                'sku' => $data->sku,
                'gtin' => $data->gtin,
                'mpn' => $data->mpn,
                'enabled' => $data->enabled,
                'weight_grams' => $data->weightGrams,
                'length_mm' => $data->lengthMm,
                'width_mm' => $data->widthMm,
                'height_mm' => $data->heightMm,
            ]);

            if ($data->name !== null || $data->shortDescription !== null || $data->descriptionHtml !== null) {
                VariantTranslation::query()->updateOrCreate(
                    ['product_variant_id' => $variant->getKey(), 'locale' => $data->locale],
                    [
                        'name' => $data->name,
                        'short_description' => $data->shortDescription,
                        'description_html' => $this->htmlFilter->clean($data->descriptionHtml),
                    ],
                );
            } else {
                VariantTranslation::query()
                    ->where('product_variant_id', $variant->getKey())
                    ->where('locale', $data->locale)
                    ->delete();
            }

            return $variant->refresh();
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return $updated;
    }
}
