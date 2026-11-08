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

final readonly class UpdateProduct
{
    public function __construct(private SkuGenerator $skuGenerator, private SlugGenerator $slugGenerator, private HtmlContentFilter $htmlFilter) {}

    public function handle(Product $product, CreateProductData $data): Product
    {
        $product = DB::transaction(function () use ($product, $data): Product {
            $publishedAt = $product->publish_at;

            if ($data->status === ProductStatus::Published->value && $publishedAt === null) {
                $publishedAt = now();
            }

            $product->update([
                'type' => $data->type,
                'status' => $data->status,
                'variant_seo_mode' => $data->variantSeoMode,
                'featured' => $data->featured,
                'unit_code' => $data->unitCode,
                'minimum_quantity' => $data->minimumQuantity,
                'maximum_quantity' => $data->maximumQuantity,
                'quantity_step' => $data->quantityStep,
                'publish_at' => $publishedAt,
                'archived_at' => $data->status === ProductStatus::Archived->value ? now() : null,
                'brand_id' => $data->brandId,
                'primary_category_id' => $data->primaryCategoryId,
            ]);

            // Slug generated once, only when this (product, locale) has no
            // translation yet — see SlugGenerator's docblock for why an
            // existing slug is never regenerated on a later name edit.
            $existingSlug = ProductTranslation::query()
                ->where('product_id', $product->getKey())->where('locale', $data->locale)->value('slug');

            ProductTranslation::query()->updateOrCreate(
                ['product_id' => $product->getKey(), 'locale' => $data->locale],
                [
                    'name' => $data->name,
                    'slug' => $existingSlug ?? $this->slugGenerator->generate($data->name, $data->locale, ProductTranslation::class, 'produkt'),
                    'short_description' => $data->shortDescription,
                    'description_html' => $this->htmlFilter->clean($data->descriptionHtml),
                ],
            );

            /** @var ProductVariant $variant */
            $variant = $product->variants()->where('is_default', true)->firstOrFail();
            $variant->update([
                'sku' => $data->sku ?? $this->skuGenerator->generate(),
                'gtin' => $data->gtin,
                'mpn' => $data->mpn,
            ]);

            $product->categories()->sync($data->categoryIds);

            return $product->refresh();
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return $product;
    }
}
