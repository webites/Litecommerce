<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\CoreProductType;
use Litecommerce\Modules\Catalog\Contracts\ProductStatus;
use Litecommerce\Modules\Catalog\Contracts\ProductUnit;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeDefinition;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeDefinitionTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValue;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValueTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Brand;
use Litecommerce\Modules\Catalog\Infrastructure\Models\BrandTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Category;
use Litecommerce\Modules\Catalog\Infrastructure\Models\CategoryTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Models\VariantTranslation;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\Media\Contracts\MediaAttachments;

final readonly class CatalogDemoDataContributor implements DemoDataContributor
{
    private const CONTENT = 'catalog::demo';

    /** Product key => brand, category, featured flag, image asset, option key (null = single variant). */
    private const PRODUCTS = [
        'fjord' => ['nord_atelier', 'ceramics', true, 'ceramic-mug', 'capacity'],
        'dune' => ['nord_atelier', 'ceramics', false, 'ceramic-mug', null],
        'tide' => ['nord_atelier', 'ceramics', true, 'ceramic-mug', null],
        'moss' => ['forma_studio', 'kitchen_textiles', true, 'linen-apron', 'size'],
        'meadow' => ['forma_studio', 'kitchen_textiles', false, 'linen-apron', null],
        'weave' => ['forma_studio', 'kitchen_textiles', false, 'linen-apron', null],
        'loop' => ['mori_works', 'wood', true, 'oak-tray', 'diameter'],
        'grain' => ['mori_works', 'wood', false, 'oak-tray', null],
        'nest' => ['mori_works', 'wood', false, 'oak-tray', null],
        'arc' => ['lumen_house', 'lighting', true, 'desk-lamp', 'color'],
        'halo' => ['lumen_house', 'lighting', false, 'desk-lamp', null],
        'ember_lamp' => ['lumen_house', 'lighting', true, 'desk-lamp', null],
        'grid' => ['paper_grid', 'stationery', true, 'notebook', 'format'],
        'focus' => ['paper_grid', 'stationery', false, 'notebook', null],
        'plain' => ['paper_grid', 'stationery', false, 'notebook', null],
        'calm' => ['scent_room', 'scents', true, 'soy-candle', 'scent'],
        'dawn' => ['scent_room', 'scents', false, 'soy-candle', null],
        'ember_candle' => ['scent_room', 'scents', false, 'soy-candle', null],
    ];

    public function __construct(
        private CreateProduct $createProduct,
        private GenerateVariants $generateVariants,
        private SlugGenerator $slugGenerator,
        private MediaAttachments $mediaAttachments,
        private DemoDataContent $demoContent,
    ) {}

    public function key(): string
    {
        return 'catalog';
    }

    public function priority(): int
    {
        return 100;
    }

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void
    {
        $content = $this->demoContent->load(self::CONTENT, $context->locale);
        $categories = $this->createCategories($context, $resources, $content);
        $brands = $this->createBrands($context, $resources, $content);
        $options = $this->createOptions($context, $resources, $content);
        $suffix = strtolower(substr($context->batchId, -6));

        $index = 0;
        foreach (self::PRODUCTS as $key => [$brand, $category, $featured, $image, $optionKey]) {
            $copy = $content['products'][$key];
            $product = $this->createProduct->handle(new CreateProductData(
                locale: $context->locale,
                name: $copy['name'],
                shortDescription: $copy['short'],
                descriptionHtml: $this->richDescription($copy, $content['templates']['sections']),
                type: CoreProductType::Physical->value,
                status: ProductStatus::Published->value,
                variantSeoMode: null,
                featured: $featured,
                unitCode: ProductUnit::Piece->value,
                minimumQuantity: '1',
                maximumQuantity: null,
                quantityStep: '1',
                sku: 'DEMO-'.str_pad((string) (++$index), 3, '0', STR_PAD_LEFT).'-'.strtoupper($suffix),
                gtin: null,
                mpn: null,
                brandId: $brands[$brand],
                primaryCategoryId: $categories[$category],
                categoryIds: [$categories[$category]],
            ));

            $valueIds = [];
            $valueNames = [];
            if ($optionKey !== null) {
                $option = $options[$optionKey];
                $valueIds = $option['values'];
                $valueNames = $content['options'][$optionKey]['values'];
                $this->generateVariants->handle($product, [$option['definition'] => $valueIds]);
            }

            $this->createVariantTranslations($product, $copy, $valueIds, $valueNames, $content['templates'], $context);

            $this->mediaAttachments->attach(
                'product',
                (int) $product->getKey(),
                'gallery',
                $this->mediaId($context, $resources, $image),
            );
            $resources->record($context->batchId, $this->key(), 'product', (int) $product->getKey());

            foreach ($product->variants()->orderBy('id')->pluck('id') as $variantId) {
                $resources->record($context->batchId, $this->key(), 'variant', (int) $variantId);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $copy
     * @param  list<int>  $valueIds
     * @param  list<string>  $valueNames
     * @param  array<string, mixed>  $templates
     */
    private function createVariantTranslations(Product $product, array $copy, array $valueIds, array $valueNames, array $templates, DemoDataContext $context): void
    {
        $variants = $product->variants()->orderBy('id')->get();
        $valueByVariant = DB::table('variant_option_values')
            ->whereIn('product_variant_id', $variants->pluck('id'))
            ->pluck('attribute_value_id', 'product_variant_id');
        $standard = $templates['standard_variant'];
        $variantTemplate = $templates['variant'];

        foreach ($variants as $variant) {
            $position = array_search((int) ($valueByVariant[(int) $variant->getKey()] ?? 0), $valueIds, true);
            $variantCopy = $position === false ? null : ($copy['variants'][$position] ?? null);
            $label = $position === false ? $standard['label'] : $valueNames[$position];
            $variantCopy ??= [
                'short' => strtr($standard['short'], [':short' => $copy['short']]),
                'intro' => strtr($standard['intro'], [':name' => $copy['name']]),
                'use' => strtr($standard['use'], [':short' => $copy['short']]),
                'detail' => strtr($standard['detail'], [':name' => $copy['name']]),
            ];

            VariantTranslation::query()->create([
                'product_variant_id' => $variant->getKey(),
                'locale' => $context->locale,
                'name' => $copy['name'].' — '.$label,
                'short_description' => $variantCopy['short'],
                'description_html' => $this->variantDescription($label, $variantCopy, $variantTemplate),
            ]);
        }
    }

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void
    {
        Product::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'product')))->delete();
        AttributeDefinition::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'attribute_definition')))->delete();
        Category::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'category')))->delete();
        Brand::query()->whereKey($this->integers($resources->ids($batchId, $this->key(), 'brand')))->delete();
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, int>
     */
    private function createCategories(DemoDataContext $context, DemoDataResourceRegistry $resources, array $content): array
    {
        $categories = [];
        $position = 0;
        foreach ($content['categories'] as $key => $definition) {
            $category = Category::query()->create(['position' => $position++, 'visible_in_tree' => true]);
            CategoryTranslation::query()->create([
                'category_id' => $category->getKey(),
                'locale' => $context->locale,
                'name' => $definition['name'],
                'slug' => $this->slugGenerator->generate($definition['name'], $context->locale, CategoryTranslation::class, $content['templates']['slug_fallbacks']['category']),
                'description_html' => '<p>'.$definition['description'].'</p>',
            ]);
            $categories[$key] = (int) $category->getKey();
            $resources->record($context->batchId, $this->key(), 'category', (int) $category->getKey());
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, int>
     */
    private function createBrands(DemoDataContext $context, DemoDataResourceRegistry $resources, array $content): array
    {
        $brands = [];
        foreach ($content['brands'] as $key => $definition) {
            $brand = Brand::query()->create();
            BrandTranslation::query()->create([
                'brand_id' => $brand->getKey(),
                'locale' => $context->locale,
                'name' => $definition['name'],
                'slug' => $this->slugGenerator->generate($definition['name'], $context->locale, BrandTranslation::class, $content['templates']['slug_fallbacks']['brand']),
                'description_html' => '<p>'.$definition['description'].'</p>',
            ]);
            $brands[$key] = (int) $brand->getKey();
            $resources->record($context->batchId, $this->key(), 'brand', (int) $brand->getKey());
        }

        return $brands;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, array{definition:int, values:list<int>}>
     */
    private function createOptions(DemoDataContext $context, DemoDataResourceRegistry $resources, array $content): array
    {
        $suffix = strtolower(substr($context->batchId, -6));
        $options = [];
        foreach ($content['options'] as $key => $definitionData) {
            $definitionKey = "demo_{$key}_{$suffix}";
            $definition = AttributeDefinition::query()->create([
                'key' => $definitionKey,
                'type' => 'single_select',
                'is_filterable' => true,
            ]);
            AttributeDefinitionTranslation::query()->create([
                'attribute_definition_id' => $definition->getKey(),
                'locale' => $context->locale,
                'name' => $definitionData['name'],
                'slug' => $this->slugGenerator->generate($definitionData['name'], $context->locale, AttributeDefinitionTranslation::class, $definitionKey),
            ]);
            $resources->record($context->batchId, $this->key(), 'attribute_definition', (int) $definition->getKey());

            $valueIds = [];
            foreach ($definitionData['values'] as $position => $name) {
                $valueKey = 'demo-'.($position + 1);
                $value = AttributeValue::query()->create([
                    'attribute_definition_id' => $definition->getKey(),
                    'value_key' => $valueKey,
                    'position' => $position,
                ]);
                AttributeValueTranslation::query()->create([
                    'attribute_value_id' => $value->getKey(),
                    'locale' => $context->locale,
                    'name' => $name,
                    'slug' => $this->slugGenerator->generate($name, $context->locale, AttributeValueTranslation::class, $valueKey),
                ]);
                $valueIds[] = (int) $value->getKey();
                $resources->record($context->batchId, $this->key(), 'attribute_value', (int) $value->getKey());
            }
            $options[$key] = ['definition' => (int) $definition->getKey(), 'values' => $valueIds];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $copy
     * @param  array<string, string>  $sections
     */
    private function richDescription(array $copy, array $sections): string
    {
        $items = implode('', array_map(static fn (string $feature): string => '<li>'.$feature.'</li>', $copy['features']));

        return '<p>'.$copy['intro'].'</p>'
            .'<h2>'.$sections['design'].'</h2><p>'.$copy['design'].'</p>'
            .'<h2>'.$sections['everyday'].'</h2><p>'.$copy['use'].'</p>'
            .'<h3>'.$sections['features'].'</h3><ul>'.$items.'</ul>'
            .'<h3>'.$sections['care'].'</h3><p>'.$copy['care'].'</p>';
    }

    /**
     * @param  array{short:string,intro:string,use:string,detail:string}  $copy
     * @param  array<string, string>  $template
     */
    private function variantDescription(string $label, array $copy, array $template): string
    {
        return '<h2>'.strtr($template['heading'], [':label' => $label]).'</h2><p>'.$copy['intro'].'</p>'
            .'<h3>'.$template['best_use'].'</h3><p>'.$copy['use'].'</p>'
            .'<h3>'.$template['good_to_know'].'</h3><p>'.$copy['detail'].'</p>';
    }

    private function mediaId(DemoDataContext $context, DemoDataResourceRegistry $resources, string $asset): int
    {
        $ids = $resources->ids($context->batchId, 'media', "asset.{$asset}");
        if (count($ids) !== 1) {
            throw new \RuntimeException("Brakuje obrazu demonstracyjnego: {$asset}.");
        }

        return (int) $ids[0];
    }

    /** @param list<string> $ids @return list<int> */
    private function integers(array $ids): array
    {
        return array_map('intval', $ids);
    }
}
