<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\AttributeType;
use Litecommerce\Modules\Catalog\Contracts\CategorySummaryData;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Catalog\Contracts\ProductOptionAxisData;
use Litecommerce\Modules\Catalog\Contracts\ProductOptionValueData;
use Litecommerce\Modules\Catalog\Contracts\ProductRelationType;
use Litecommerce\Modules\Catalog\Contracts\ProductStatus;
use Litecommerce\Modules\Catalog\Contracts\ProductSummaryData;
use Litecommerce\Modules\Catalog\Contracts\ProductViewData;
use Litecommerce\Modules\Catalog\Contracts\VariantSummaryData;
use Litecommerce\Modules\Catalog\Infrastructure\Models\CatalogSeoSettings;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductRelation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Litecommerce\Modules\Media\Contracts\MediaAttachments;
use Litecommerce\Modules\Media\Contracts\MediaData;

final class EloquentProductCatalog implements ProductCatalog
{
    /**
     * Memoized per instance (not per call) so a single request touching many
     * products — a listing page's whole ->map() — only reads
     * catalog_seo_settings once, not once per product.
     */
    private ?string $cachedDefaultVariantSeoMode = null;

    public function __construct(private readonly MediaAttachments $media) {}

    /** A product's own variant_seo_mode when set, else the store-wide default — see catalog_seo_settings. */
    private function effectiveVariantSeoMode(?string $override): string
    {
        if ($override !== null) {
            return $override;
        }

        return $this->cachedDefaultVariantSeoMode ??= CatalogSeoSettings::current()->default_variant_seo_mode;
    }

    public function findSlugInLocale(int $id, string $locale): ?string
    {
        $slug = Product::query()
            ->where('id', $id)
            ->where('status', ProductStatus::Published->value)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with(['translations' => fn ($q) => $q->where('locale', $locale)])
            ->first()
            ?->translations->first()?->slug;

        return is_string($slug) ? $slug : null;
    }

    public function findBySlug(string $slug, string $locale): ?ProductViewData
    {
        $product = Product::query()
            ->where('status', ProductStatus::Published->value)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('slug', $slug))
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'brand.translations' => fn ($q) => $q->where('locale', $locale),
                'categories.translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('enabled', true),
                'variants.translations' => fn ($q) => $q->where('locale', $locale),
            ])
            ->first();

        if ($product === null) {
            return null;
        }

        $translation = $product->translations->first();
        $translatedName = $translation === null ? '' : $translation->name;
        $brandTranslation = $product->brand?->translations->first();
        // Dropping a media with no public URL (e.g. a non-public disk) from imageUrls
        // alone would desync it from imageSrcsets/imageWidths/imageHeights, which must
        // stay the same length and positionally parallel — filter once, upfront.
        $images = array_values(array_filter(
            $this->media->list('product', (int) $product->getKey(), 'gallery'),
            static fn (MediaData $media): bool => $media->url !== null,
        ));
        $relatedProducts = $this->relatedProducts((int) $product->getKey(), $locale);
        $variantIds = $product->variants->map(static fn (ProductVariant $variant): int => (int) $variant->getKey())->all();
        ['axes' => $optionAxes, 'assignments' => $optionAssignments] = $this->optionAxesAndAssignments($variantIds, $locale);

        return new ProductViewData(
            id: (int) $product->getKey(),
            slug: $translation === null ? $slug : $translation->slug,
            type: $product->type,
            variantSeoMode: $this->effectiveVariantSeoMode($product->variant_seo_mode),
            name: $translatedName,
            shortDescription: $translation?->short_description,
            descriptionHtml: $translation?->description_html,
            brandName: $brandTranslation === null ? null : $brandTranslation->name,
            categories: $product->categories->map(function ($category): CategorySummaryData {
                $categoryTranslation = $category->translations->first();

                return new CategorySummaryData(
                    (int) $category->getKey(),
                    $categoryTranslation === null ? '' : $categoryTranslation->name,
                    $categoryTranslation === null ? '' : $categoryTranslation->slug,
                );
            })->values()->all(),
            imageUrls: array_map(static fn (MediaData $media): string => (string) $media->url, $images),
            imageSrcsets: array_map(static fn (MediaData $media): ?string => $media->srcset(), $images),
            imageWidths: array_map(static fn (MediaData $media): ?int => $media->width, $images),
            imageHeights: array_map(static fn (MediaData $media): ?int => $media->height, $images),
            variants: $product->variants->map(fn (ProductVariant $variant): VariantSummaryData => new VariantSummaryData(
                id: (int) $variant->getKey(), productId: $product->getKey(), sku: $variant->sku,
                gtin: $variant->gtin ?? '', mpn: $variant->mpn ?? '',
                displayName: $this->variantDisplayName($variant, $translatedName),
                enabled: $variant->enabled, productType: $product->type,
                minimumQuantity: $product->minimum_quantity, maximumQuantity: $product->maximum_quantity,
                quantityStep: $product->quantity_step,
                optionValues: $optionAssignments[(int) $variant->getKey()] ?? [],
                shortDescription: $variant->translations->first()?->short_description,
                descriptionHtml: $variant->translations->first()?->description_html,
            ))->values()->all(),
            optionAxes: $optionAxes,
            upsells: $relatedProducts[ProductRelationType::Upsell->value],
            crossSells: $relatedProducts[ProductRelationType::CrossSell->value],
        );
    }

    /**
     * Reads product_options/product_option_values/variant_option_values
     * directly (query builder, not Eloquent models — none exist for these
     * join tables; the panel's ProductController reads them the same way)
     * to build the picker's axes (deduplicated, ordered by position, only
     * values at least one of the given — already enabled-filtered —
     * variants actually uses) and, per variant, its own axis-key =>
     * value-key assignment, so a theme can resolve a picker selection to
     * one concrete variant by simple key comparison, without needing to
     * replicate product_variants.option_signature's hashing client-side.
     *
     * @param  list<int>  $variantIds
     * @return array{axes: list<ProductOptionAxisData>, assignments: array<int, array<string, string>>}
     */
    private function optionAxesAndAssignments(array $variantIds, string $locale): array
    {
        if ($variantIds === []) {
            return ['axes' => [], 'assignments' => []];
        }

        $rows = DB::table('variant_option_values as vov')
            ->join('product_options as po', 'po.id', '=', 'vov.product_option_id')
            ->join('attribute_definitions as ad', 'ad.id', '=', 'po.attribute_definition_id')
            ->join('attribute_definition_translations as dt', function ($join) use ($locale): void {
                $join->on('dt.attribute_definition_id', '=', 'ad.id')->where('dt.locale', $locale);
            })
            ->join('attribute_values as av', 'av.id', '=', 'vov.attribute_value_id')
            ->join('attribute_value_translations as vt', function ($join) use ($locale): void {
                $join->on('vt.attribute_value_id', '=', 'av.id')->where('vt.locale', $locale);
            })
            ->whereIn('vov.product_variant_id', $variantIds)
            ->orderBy('po.position')->orderBy('av.position')
            ->get([
                'vov.product_variant_id',
                'po.attribute_definition_id', 'ad.key as definition_key', 'ad.type as definition_type', 'dt.name as definition_name', 'dt.slug as definition_slug',
                'av.id as value_id', 'av.value_key', 'av.color_hex', 'vt.name as value_name', 'vt.slug as value_slug',
            ]);

        $axisMeta = [];
        $valuesByDefinitionId = [];
        $seenValueIds = [];
        $assignments = [];

        foreach ($rows as $row) {
            $definitionId = (int) $row->attribute_definition_id;

            if (! isset($axisMeta[$definitionId])) {
                $axisMeta[$definitionId] = [
                    'key' => $row->definition_key,
                    'name' => $row->definition_name,
                    'slug' => $row->definition_slug,
                    'isColor' => $row->definition_type === AttributeType::Color->value,
                ];
                $valuesByDefinitionId[$definitionId] = [];
            }

            $valueId = (int) $row->value_id;
            if (! isset($seenValueIds[$definitionId][$valueId])) {
                $seenValueIds[$definitionId][$valueId] = true;
                $valuesByDefinitionId[$definitionId][] = new ProductOptionValueData(
                    id: $valueId, key: $row->value_key, name: $row->value_name, slug: $row->value_slug, colorHex: $row->color_hex,
                );
            }

            $assignments[(int) $row->product_variant_id][$row->definition_key] = $row->value_key;
        }

        $axes = [];
        foreach ($axisMeta as $definitionId => $meta) {
            $axes[] = new ProductOptionAxisData(
                definitionId: $definitionId,
                key: $meta['key'],
                name: $meta['name'],
                slug: $meta['slug'],
                isColor: $meta['isColor'],
                values: $valuesByDefinitionId[$definitionId],
            );
        }

        return ['axes' => $axes, 'assignments' => $assignments];
    }

    /**
     * Manually curated links (Application\SetProductRelations) may point at
     * a product that has since been unpublished or deleted — silently
     * dropped here rather than surfaced, since the storefront must never
     * link to something a customer can't actually view or buy.
     *
     * @return array<string, list<ProductSummaryData>> keyed by ProductRelationType value
     */
    private function relatedProducts(int $productId, string $locale): array
    {
        $result = [ProductRelationType::Upsell->value => [], ProductRelationType::CrossSell->value => []];

        $relations = ProductRelation::query()->where('source_product_id', $productId)
            ->whereNotNull('target_product_id')
            ->orderBy('type')->orderBy('position')->get();

        if ($relations->isEmpty()) {
            return $result;
        }

        $targetIds = $relations->pluck('target_product_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $targets = Product::query()->whereIn('id', $targetIds)
            ->where('status', ProductStatus::Published->value)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('is_default', true),
            ])->get()->keyBy('id');

        foreach ($relations as $relation) {
            $target = $targets->get($relation->target_product_id);

            if ($target === null || ! isset($result[$relation->type])) {
                continue;
            }

            $result[$relation->type][] = $this->toSummary($target);
        }

        return $result;
    }

    /** @param list<MediaData>|null $images */
    private function toSummary(Product $product, ?array $images = null): ProductSummaryData
    {
        $translation = $product->translations->first();
        $images ??= $this->media->list('product', (int) $product->getKey(), 'gallery');
        $coverImage = $images[0] ?? null;
        $defaultVariant = $product->variants->first();

        return new ProductSummaryData(
            id: (int) $product->getKey(),
            slug: $translation === null ? '' : $translation->slug,
            type: $product->type,
            name: $translation === null ? '' : $translation->name,
            shortDescription: $translation?->short_description,
            imageUrl: $coverImage?->url,
            defaultVariantId: $defaultVariant === null ? 0 : (int) $defaultVariant->getKey(),
            variantSeoMode: $this->effectiveVariantSeoMode($product->variant_seo_mode),
            imageSrcset: $coverImage?->srcset(),
            imageWidth: $coverImage?->width,
            imageHeight: $coverImage?->height,
        );
    }

    public function paginatePublished(string $locale, ?int $categoryId, int $perPage, int $page): array
    {
        $query = Product::query()
            ->where('status', ProductStatus::Published->value)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->when($categoryId !== null, fn ($q) => $q->whereHas('categories', fn ($q) => $q->where('categories.id', $categoryId)))
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('is_default', true),
            ])
            ->orderByDesc('publish_at');

        $total = (clone $query)->count();
        $products = $query->forPage($page, $perPage)->get();

        $items = $products->map(fn (Product $product): ProductSummaryData => $this->toSummary($product))->values()->all();

        return ['items' => $items, 'total' => $total];
    }

    public function paginateByBrand(string $locale, int $brandId, int $perPage, int $page): array
    {
        $query = Product::query()
            ->where('status', ProductStatus::Published->value)
            ->where('brand_id', $brandId)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('is_default', true),
            ])
            ->orderByDesc('publish_at');

        $total = (clone $query)->count();
        $products = $query->forPage($page, $perPage)->get();

        $items = $products->map(fn (Product $product): ProductSummaryData => $this->toSummary($product))->values()->all();

        return ['items' => $items, 'total' => $total];
    }

    public function paginateFeatured(string $locale, int $perPage, int $page): array
    {
        $query = Product::query()
            ->where('status', ProductStatus::Published->value)
            ->where('featured', true)
            ->whereHas('translations', fn ($q) => $q->where('locale', $locale))
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('is_default', true),
            ])
            ->orderByDesc('publish_at');

        $total = (clone $query)->count();
        $products = $query->forPage($page, $perPage)->get();

        $items = $products->map(fn (Product $product): ProductSummaryData => $this->toSummary($product))->values()->all();

        return ['items' => $items, 'total' => $total];
    }

    public function findManyPublished(array $ids, string $locale): array
    {
        if ($ids === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('status', ProductStatus::Published->value)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('is_default', true),
            ])
            ->get()
            ->keyBy('id');
        $media = $this->media->listMany('product', $ids, 'gallery');

        return array_values(array_filter(array_map(
            fn (int $id): ?ProductSummaryData => $products->has($id) ? $this->toSummary($products->get($id), $media[$id] ?? []) : null,
            $ids,
        )));
    }

    public function search(string $term, string $locale, int $limit): array
    {
        $term = trim($term);
        $query = Product::query()
            ->where('status', ProductStatus::Published->value)
            ->with([
                'translations' => fn ($q) => $q->where('locale', $locale),
                'variants' => fn ($q) => $q->where('is_default', true),
            ]);

        if ($term !== '') {
            $query->where(function ($outer) use ($term, $locale): void {
                $outer->whereHas('translations', fn ($t) => $t->where('locale', $locale)->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%"));
            });
        }

        return $query->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Product $product): ProductSummaryData => $this->toSummary($product))
            ->values()->all();
    }

    private function variantDisplayName(ProductVariant $variant, string $productName): string
    {
        $variantTranslation = $variant->translations->first();
        $variantName = $variantTranslation?->name;

        return $variantName !== null && $variantName !== '' ? $variantName : $productName;
    }
}
