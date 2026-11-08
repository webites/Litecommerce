<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Contracts\ProductStatus;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;

final readonly class CreateProduct
{
    public function __construct(private SkuGenerator $skuGenerator, private SlugGenerator $slugGenerator, private HtmlContentFilter $htmlFilter) {}

    public function handle(CreateProductData $data): Product
    {
        $product = DB::transaction(function () use ($data): Product {
            $product = Product::query()->create([
                'type' => $data->type,
                'status' => $data->status,
                'variant_seo_mode' => $data->variantSeoMode,
                'featured' => $data->featured,
                'unit_code' => $data->unitCode,
                'minimum_quantity' => $data->minimumQuantity,
                'maximum_quantity' => $data->maximumQuantity,
                'quantity_step' => $data->quantityStep,
                'publish_at' => $data->status === ProductStatus::Published->value ? now() : null,
                'archived_at' => $data->status === ProductStatus::Archived->value ? now() : null,
                'brand_id' => $data->brandId,
                'primary_category_id' => $data->primaryCategoryId,
            ]);

            ProductTranslation::query()->create([
                'product_id' => $product->getKey(),
                'locale' => $data->locale,
                'name' => $data->name,
                'slug' => $this->slugGenerator->generate($data->name, $data->locale, ProductTranslation::class, 'produkt'),
                'short_description' => $data->shortDescription,
                'description_html' => $this->htmlFilter->clean($data->descriptionHtml),
            ]);

            ProductVariant::query()->create([
                'product_id' => $product->getKey(),
                'sku' => $data->sku ?? $this->skuGenerator->generate(),
                'gtin' => $data->gtin,
                'mpn' => $data->mpn,
                'enabled' => true,
                'is_default' => true,
            ]);

            $product->categories()->sync($data->categoryIds);

            return $product;
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return $product;
    }
}
