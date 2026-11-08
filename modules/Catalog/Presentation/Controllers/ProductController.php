<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Events\CommittedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Catalog\Application\CreateProduct;
use Litecommerce\Modules\Catalog\Application\CreateProductData;
use Litecommerce\Modules\Catalog\Application\GenerateVariants;
use Litecommerce\Modules\Catalog\Application\SetProductAttributes;
use Litecommerce\Modules\Catalog\Application\SetProductRelations;
use Litecommerce\Modules\Catalog\Application\UpdateProduct;
use Litecommerce\Modules\Catalog\Application\UpdateVariant;
use Litecommerce\Modules\Catalog\Application\UpdateVariantData;
use Litecommerce\Modules\Catalog\Contracts\CatalogSeoSettingsRepository;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Contracts\ProductRelationType;
use Litecommerce\Modules\Catalog\Contracts\ProductStatus;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Catalog\Contracts\ProductUnit;
use Litecommerce\Modules\Catalog\Contracts\VariantSeoMode;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeDefinition;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValue;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Brand;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Category;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Product;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductRelation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Litecommerce\Modules\Catalog\Presentation\Requests\GenerateVariantsRequest;
use Litecommerce\Modules\Catalog\Presentation\Requests\SetProductAttributesRequest;
use Litecommerce\Modules\Catalog\Presentation\Requests\SetProductRelationsRequest;
use Litecommerce\Modules\Catalog\Presentation\Requests\StoreProductRequest;
use Litecommerce\Modules\Catalog\Presentation\Requests\UpdateProductRequest;
use Litecommerce\Modules\Catalog\Presentation\Requests\UpdateVariantRequest;
use Litecommerce\Modules\Digital\Contracts\DigitalFileCatalog;
use Litecommerce\Modules\Digital\Contracts\DigitalFileData;
use Litecommerce\Modules\Digital\Contracts\DigitalFileManager;
use Litecommerce\Modules\Media\Contracts\MediaAttachments;
use Litecommerce\Modules\Media\Contracts\MediaData;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PriceQuoter;
use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentCatalog;
use Litecommerce\Modules\Pricing\Contracts\ProductTaxAssignmentManager;
use Litecommerce\Modules\Pricing\Contracts\TaxRateCatalog;
use Litecommerce\Modules\Pricing\Contracts\TaxRateData;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceCatalog;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceManager;
use Litecommerce\Modules\Store\Contracts\CurrencyData;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;
use Litecommerce\Modules\Warehouse\Contracts\StockLevelCatalog;

final class ProductController extends Controller
{
    public function index(LanguageRepository $languages): Response
    {
        $locale = $this->defaultLocale($languages);

        return Inertia::render('Panel/Catalog/Index', [
            'products' => Product::query()
                ->with([
                    'translations' => fn ($query) => $query->where('locale', $locale),
                    'variants' => fn ($query) => $query->where('is_default', true),
                ])
                ->latest('id')
                ->paginate(25)
                ->through(static function (Product $product): array {
                    $translation = $product->translations->first();
                    $variant = $product->variants->first();

                    return [
                        'id' => (int) $product->getKey(),
                        'name' => $translation === null ? 'Brak tłumaczenia' : $translation->name,
                        'type' => $product->type,
                        'status' => $product->status,
                        'sku' => $variant?->sku,
                    ];
                }),
        ]);
    }

    public function create(LanguageRepository $languages, CatalogSeoSettingsRepository $seoSettings): Response
    {
        return Inertia::render('Panel/Catalog/Create', $this->formOptions($languages, $seoSettings));
    }

    public function edit(
        Product $product,
        LanguageRepository $languages,
        CatalogSeoSettingsRepository $seoSettings,
        MediaAttachments $mediaAttachments,
        TaxRateCatalog $taxRates,
        ProductTaxAssignmentCatalog $taxAssignments,
        VariantPriceCatalog $variantPrices,
        PriceQuoter $quoter,
        CurrencyRepository $currencies,
        StockLevelCatalog $stockLevels,
        DigitalFileCatalog $digitalFiles,
    ): Response {
        $locale = $this->defaultLocale($languages);
        $baseCurrency = $currencies->base();
        $baseCurrencyCode = $baseCurrency !== null ? $baseCurrency->code : '';
        $translation = $product->translations()->where('locale', $locale)->first();

        if ($translation === null) {
            $translation = $product->translations()->firstOrFail();
        }

        $variant = $product->variants()->where('is_default', true)->firstOrFail();
        $optionLabels = DB::table('variant_option_values')
            ->join('attribute_value_translations', 'attribute_value_translations.attribute_value_id', '=', 'variant_option_values.attribute_value_id')
            ->join('product_options', 'product_options.id', '=', 'variant_option_values.product_option_id')
            ->join('attribute_definition_translations', 'attribute_definition_translations.attribute_definition_id', '=', 'product_options.attribute_definition_id')
            ->where('product_options.product_id', $product->getKey())
            ->where('attribute_value_translations.locale', $locale)
            ->where('attribute_definition_translations.locale', $locale)
            ->orderBy('product_options.position')
            ->get(['variant_option_values.product_variant_id', 'attribute_definition_translations.name as attribute_name', 'attribute_value_translations.name as value_name'])
            ->groupBy('product_variant_id');
        $attributeRows = DB::table('product_attribute_values')
            ->where('product_id', $product->getKey())
            ->orderBy('position')->orderBy('id')->get()->groupBy('attribute_definition_id');
        $attributeDefinitions = AttributeDefinition::query()->with([
            'translations' => fn ($query) => $query->where('locale', $locale),
            'values.translations' => fn ($query) => $query->where('locale', $locale),
        ])->orderBy('key')->get()->map(static function (AttributeDefinition $definition) use ($attributeRows): array {
            $translation = $definition->translations->first();
            $rows = $attributeRows->get($definition->getKey(), collect());
            $first = $rows->first();

            return [
                'attribute_definition_id' => (int) $definition->getKey(),
                'name' => $translation === null ? $definition->key : $translation->name,
                'type' => $definition->type,
                'assigned' => $rows->isNotEmpty(),
                'value_ids' => $rows->pluck('attribute_value_id')->filter()->map(static fn (mixed $id): int => (int) $id)->values()->all(),
                'text_value' => $first === null || $first->text_value === null ? '' : (string) $first->text_value,
                'number_value' => $first === null || $first->number_value === null ? '' : (string) $first->number_value,
                'boolean_value' => $first !== null && (bool) $first->boolean_value,
                'values' => $definition->values->map(static function (AttributeValue $value): array {
                    $translation = $value->translations->first();

                    return ['id' => (int) $value->getKey(), 'name' => $translation === null ? $value->value_key : $translation->name, 'color_hex' => $value->color_hex];
                })->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('Panel/Catalog/Edit', [
            ...$this->formOptions($languages, $seoSettings),
            // Only a product with at least one generated variant-defining axis
            // (product_options rows) has any "variant page" for the SEO mode
            // select to mean anything — a product without axes never gets a
            // variant deep-link URL (see CatalogController::matchVariantPath()).
            'hasAxes' => DB::table('product_options')->where('product_id', $product->getKey())->exists(),
            'product' => [
                'id' => (int) $product->getKey(),
                'locale' => $translation->locale,
                'name' => $translation->name,
                'short_description' => $translation->short_description ?? '',
                'description_html' => $translation->description_html ?? '',
                'type' => $product->type,
                'status' => $product->status,
                'variant_seo_mode' => $product->variant_seo_mode ?? '',
                'featured' => $product->featured,
                'unit_code' => $product->unit_code,
                'minimum_quantity' => $product->minimum_quantity,
                'maximum_quantity' => $product->maximum_quantity ?? '',
                'quantity_step' => $product->quantity_step,
                'sku' => $variant->sku,
                'gtin' => $variant->gtin ?? '',
                'mpn' => $variant->mpn ?? '',
                'brand_id' => $product->brand_id,
                'primary_category_id' => $product->primary_category_id,
                'category_ids' => $product->categories()->pluck('categories.id')->map(static fn (mixed $id): int => (int) $id)->all(),
                'tax_rate_id' => $taxAssignments->find((int) $product->getKey()),
            ],
            'variants' => $product->variants()->with(['translations' => fn ($query) => $query->where('locale', $locale)])
                ->orderByDesc('is_default')->orderBy('id')->get()->map(function (ProductVariant $variant) use ($optionLabels, $variantPrices, $quoter, $baseCurrencyCode, $stockLevels, $digitalFiles, $product): array {
                    $labels = $optionLabels->get($variant->getKey(), collect());
                    $translation = $variant->translations->first();

                    return [
                        'id' => (int) $variant->getKey(),
                        'sku' => $variant->sku,
                        'enabled' => $variant->enabled,
                        'is_default' => $variant->is_default,
                        'options' => $labels->map(static fn (object $row): string => $row->attribute_name.': '.$row->value_name)->values()->all(),
                        'locale' => $translation === null ? null : $translation->locale,
                        'name' => $translation === null ? '' : ($translation->name ?? ''),
                        'short_description' => $translation === null ? '' : ($translation->short_description ?? ''),
                        'description_html' => $translation === null ? '' : ($translation->description_html ?? ''),
                        'gtin' => $variant->gtin ?? '',
                        'mpn' => $variant->mpn ?? '',
                        'weight_grams' => $variant->weight_grams ?? '',
                        'length_mm' => $variant->length_mm ?? '',
                        'width_mm' => $variant->width_mm ?? '',
                        'height_mm' => $variant->height_mm ?? '',
                        'price' => $this->variantPriceData((int) $variant->getKey(), $variantPrices, $quoter, $baseCurrencyCode),
                        'stock' => $product->type === ProductType::Physical->value
                            ? $this->variantStockData((int) $variant->getKey(), $stockLevels)
                            : null,
                        'digital_files' => $product->type === ProductType::Digital->value
                            ? array_map(fn (DigitalFileData $file): array => $this->digitalFileData($file), $digitalFiles->listForVariant((int) $variant->getKey()))
                            : null,
                    ];
                })->values()->all(),
            'productAttributes' => $attributeDefinitions,
            'images' => array_map(static fn (MediaData $image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'original_filename' => $image->originalFilename,
            ], $mediaAttachments->list('product', (int) $product->getKey(), 'gallery')),
            'relations' => $this->relationsData($product, $locale),
            'relationTypes' => [
                ['value' => ProductRelationType::Upsell->value, 'label' => 'Upsell'],
                ['value' => ProductRelationType::CrossSell->value, 'label' => 'Cross-sell'],
            ],
            'taxRates' => array_map(fn (TaxRateData $rate): array => [
                'id' => $rate->id,
                'name' => $rate->name,
                'kind' => $rate->kind->value,
                'percentage' => $this->trimDecimal($rate->percentage),
            ], $taxRates->all()),
            'baseCurrencyCode' => $baseCurrencyCode,
            'currencies' => array_values(array_filter(
                array_map(static fn (CurrencyData $currency): array => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'is_base' => $currency->isBase,
                ], $currencies->all()),
                static fn (array $currency): bool => ! $currency['is_base'],
            )),
        ]);
    }

    /** @return list<array{type:string,target_product_id:int,name:string,sku:?string}> */
    private function relationsData(Product $product, string $locale): array
    {
        $rows = ProductRelation::query()->where('source_product_id', $product->getKey())
            ->orderBy('type')->orderBy('position')->get();
        $targetIds = $rows->pluck('target_product_id')->filter()->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $targets = Product::query()->whereIn('id', $targetIds)
            ->with([
                'translations' => fn ($query) => $query->where('locale', $locale),
                'variants' => fn ($query) => $query->where('is_default', true),
            ])->get()->keyBy('id');

        return $rows->map(function (ProductRelation $relation) use ($targets): array {
            $target = $relation->target_product_id === null ? null : $targets->get($relation->target_product_id);
            $translation = $target?->translations->first();
            $variant = $target?->variants->first();

            return [
                'type' => $relation->type,
                'target_product_id' => (int) $relation->target_product_id,
                'name' => $translation === null ? 'Produkt #'.$relation->target_product_id : $translation->name,
                'sku' => $variant?->sku,
            ];
        })->values()->all();
    }

    public function generateVariants(
        GenerateVariantsRequest $request,
        Product $product,
        GenerateVariants $generator,
        AuditLogger $audit,
    ): RedirectResponse {
        /** @var array{options:list<array{attribute_definition_id:int,attribute_value_ids:list<int>}>} $validated */
        $validated = $request->validated();
        $selectedValues = [];

        foreach ($validated['options'] as $option) {
            $selectedValues[$option['attribute_definition_id']] = array_map('intval', $option['attribute_value_ids']);
        }

        $created = $generator->handle($product, $selectedValues);
        $this->audit($request, $audit, 'catalog.variants.generated', $product, ['created_count' => $created]);

        return back()->with('status', $created === 0 ? 'Wszystkie wybrane kombinacje już istnieją.' : "Utworzono warianty: {$created}.");
    }

    public function updateVariant(
        UpdateVariantRequest $request,
        Product $product,
        ProductVariant $variant,
        UpdateVariant $updater,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($variant->product_id === (int) $product->getKey(), 404);
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $before = ['sku' => $variant->sku, 'gtin' => $variant->gtin, 'mpn' => $variant->mpn, 'enabled' => $variant->enabled];

        $variant = $updater->handle($variant, new UpdateVariantData(
            locale: (string) $validated['locale'],
            sku: (string) $validated['sku'],
            gtin: isset($validated['gtin']) ? (string) $validated['gtin'] : null,
            mpn: isset($validated['mpn']) ? (string) $validated['mpn'] : null,
            enabled: (bool) $validated['enabled'],
            name: isset($validated['name']) ? (string) $validated['name'] : null,
            shortDescription: isset($validated['short_description']) ? (string) $validated['short_description'] : null,
            descriptionHtml: isset($validated['description_html']) ? (string) $validated['description_html'] : null,
            weightGrams: isset($validated['weight_grams']) ? (int) $validated['weight_grams'] : null,
            lengthMm: isset($validated['length_mm']) ? (int) $validated['length_mm'] : null,
            widthMm: isset($validated['width_mm']) ? (int) $validated['width_mm'] : null,
            heightMm: isset($validated['height_mm']) ? (int) $validated['height_mm'] : null,
        ));

        $this->audit($request, $audit, 'catalog.variant.updated', $product, [
            'variant_id' => (int) $variant->getKey(),
            'before' => $before,
            'after' => ['sku' => $variant->sku, 'gtin' => $variant->gtin, 'mpn' => $variant->mpn, 'enabled' => $variant->enabled],
        ]);

        return back()->with('status', 'Wariant został zapisany.');
    }

    public function updateTax(
        Request $request,
        Product $product,
        ProductTaxAssignmentManager $assignments,
        AuditLogger $audit,
    ): RedirectResponse {
        /** @var array{tax_rate_id:int|null} $validated */
        $validated = $request->validate([
            'tax_rate_id' => ['nullable', 'integer', 'exists:tax_rates,id'],
        ]);
        $taxRateId = $validated['tax_rate_id'] ?? null;
        $assignments->assign((int) $product->getKey(), $taxRateId);

        $this->audit($request, $audit, 'catalog.product.tax_assigned', $product, ['tax_rate_id' => $taxRateId]);

        return back()->with('status', 'Stawka VAT produktu została zapisana.');
    }

    public function updateVariantPrice(
        Request $request,
        Product $product,
        ProductVariant $variant,
        CurrencyRepository $currencies,
        VariantPriceManager $manager,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($variant->product_id === (int) $product->getKey(), 404);
        $base = $currencies->base();
        abort_if($base === null, 422, 'Waluta bazowa nie jest jeszcze ustawiona.');

        /** @var array{regular_amount:string,regular_mode:string,promotional_amount:string|null,promotional_mode:string|null} $validated */
        $validated = $request->validate([
            'regular_amount' => ['required', 'numeric', 'gte:0', 'decimal:0,'.$base->decimalPlaces],
            'regular_mode' => ['required', Rule::enum(PriceMode::class)],
            'promotional_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,'.$base->decimalPlaces],
            'promotional_mode' => ['required_with:promotional_amount', 'nullable', Rule::enum(PriceMode::class)],
        ]);

        $manager->setBasePrice(
            variantId: (int) $variant->getKey(),
            regularAmount: $validated['regular_amount'],
            regularMode: PriceMode::from($validated['regular_mode']),
            promotionalAmount: $validated['promotional_amount'] ?? null,
            promotionalMode: isset($validated['promotional_mode']) ? PriceMode::from($validated['promotional_mode']) : null,
        );

        $this->audit($request, $audit, 'catalog.variant.price_updated', $product, [
            'variant_id' => (int) $variant->getKey(),
            'regular_amount' => $validated['regular_amount'],
            'promotional_amount' => $validated['promotional_amount'] ?? null,
        ]);

        return back()->with('status', 'Cena wariantu została zapisana.');
    }

    public function updateVariantPriceOverride(
        Request $request,
        Product $product,
        ProductVariant $variant,
        string $currencyCode,
        CurrencyRepository $currencies,
        VariantPriceManager $manager,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($variant->product_id === (int) $product->getKey(), 404);
        $currency = $currencies->find(strtoupper($currencyCode));
        abort_if($currency === null || $currency->isBase, 404);

        /** @var array{regular_amount:string|null,regular_mode:string|null,promotional_amount:string|null,promotional_mode:string|null} $validated */
        $validated = $request->validate([
            'regular_amount' => ['nullable', 'numeric', 'gte:0', 'decimal:0,'.$currency->decimalPlaces],
            'regular_mode' => ['required_with:regular_amount', 'nullable', Rule::enum(PriceMode::class)],
            'promotional_amount' => ['nullable', 'numeric', 'gt:0', 'decimal:0,'.$currency->decimalPlaces],
            'promotional_mode' => ['required_with:promotional_amount', 'nullable', Rule::enum(PriceMode::class)],
        ]);

        $manager->setCurrencyOverride(
            variantId: (int) $variant->getKey(),
            currencyCode: $currency->code,
            regularAmount: $validated['regular_amount'] ?? null,
            regularMode: isset($validated['regular_mode']) ? PriceMode::from($validated['regular_mode']) : null,
            promotionalAmount: $validated['promotional_amount'] ?? null,
            promotionalMode: isset($validated['promotional_mode']) ? PriceMode::from($validated['promotional_mode']) : null,
        );

        $this->audit($request, $audit, 'catalog.variant.price_override_updated', $product, [
            'variant_id' => (int) $variant->getKey(),
            'currency_code' => $currency->code,
        ]);

        return back()->with('status', "Nadpisanie ceny w walucie {$currency->code} zostało zapisane.");
    }

    /** @return array{regular_amount:string,regular_mode:string,promotional_amount:?string,promotional_mode:?string,overrides:array<string,array{regular_amount:?string,regular_mode:?string,promotional_amount:?string,promotional_mode:?string}>,base_quote:?array{net:string,gross:string,promotional_active:bool}} */
    private function variantPriceData(int $variantId, VariantPriceCatalog $prices, PriceQuoter $quoter, string $baseCurrencyCode): array
    {
        $price = $prices->find($variantId);
        $overrides = [];

        if ($price !== null) {
            foreach ($price->overrides as $override) {
                $overrides[$override->currencyCode] = [
                    'regular_amount' => $this->trimDecimal($override->regularAmount),
                    'regular_mode' => $override->regularMode?->value,
                    'promotional_amount' => $this->trimDecimal($override->promotionalAmount),
                    'promotional_mode' => $override->promotionalMode?->value,
                ];
            }
        }

        $quote = $baseCurrencyCode === '' || $price === null ? null : $quoter->quote($variantId, $baseCurrencyCode, '1');

        return [
            'has_price' => $price !== null,
            'regular_amount' => $this->trimDecimal($price?->regularAmount) ?? '',
            'regular_mode' => $price?->regularMode->value ?? 'gross',
            'promotional_amount' => $this->trimDecimal($price?->promotionalAmount),
            'promotional_mode' => $price?->promotionalMode?->value,
            'overrides' => $overrides,
            'base_quote' => $quote === null ? null : [
                'net' => $quote->lineNetAmount,
                'gross' => $quote->lineGrossAmount,
                'promotional_active' => $quote->promotionalActive,
            ],
        ];
    }

    /** @return array{on_hand_quantity:string,reserved_quantity:string,available_quantity:string,allow_backorder:bool} */
    private function variantStockData(int $variantId, StockLevelCatalog $stockLevels): array
    {
        $stock = $stockLevels->find($variantId);

        if ($stock === null) {
            return ['on_hand_quantity' => '0', 'reserved_quantity' => '0', 'available_quantity' => '0', 'allow_backorder' => false];
        }

        return [
            'on_hand_quantity' => $this->trimDecimal($stock->onHandQuantity) ?? '0',
            'reserved_quantity' => $this->trimDecimal($stock->reservedQuantity) ?? '0',
            'available_quantity' => $this->trimDecimal($stock->availableQuantity) ?? '0',
            'allow_backorder' => $stock->allowBackorder,
        ];
    }

    /** @return array{id:int,label:string,active:bool,max_downloads:?int,validity_days:?int,current_version:?array{id:int,version_number:int,original_filename:string,size_bytes:int}} */
    private function digitalFileData(DigitalFileData $file): array
    {
        return [
            'id' => $file->id,
            'label' => $file->label,
            'active' => $file->active,
            'max_downloads' => $file->maxDownloads,
            'validity_days' => $file->validityDays,
            'current_version' => $file->currentVersion === null ? null : [
                'id' => $file->currentVersion->id,
                'version_number' => $file->currentVersion->versionNumber,
                'original_filename' => $file->currentVersion->originalFilename,
                'size_bytes' => $file->currentVersion->sizeBytes,
            ],
        ];
    }

    /**
     * Prices are stored at a fixed 6-decimal precision (like Catalog's own
     * quantities); trims the display copy back to what was actually
     * entered, e.g. "19.990000" -> "19.99", "20.000000" -> "20".
     */
    private function trimDecimal(?string $value): ?string
    {
        if ($value === null || ! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }

    public function setAttributes(
        SetProductAttributesRequest $request,
        Product $product,
        SetProductAttributes $setter,
        AuditLogger $audit,
    ): RedirectResponse {
        /** @var array{attributes:list<array{attribute_definition_id:int,value_ids:list<int>,text_value?:string|null,number_value?:string|null,boolean_value?:bool|null}>} $validated */
        $validated = $request->validated();
        $attributes = array_map(static fn (array $attribute): array => [
            'attribute_definition_id' => $attribute['attribute_definition_id'],
            'value_ids' => array_map('intval', $attribute['value_ids']),
            'text_value' => isset($attribute['text_value']) ? trim($attribute['text_value']) : null,
            'number_value' => isset($attribute['number_value']) ? $attribute['number_value'] : null,
            'boolean_value' => isset($attribute['boolean_value']) ? (bool) $attribute['boolean_value'] : null,
        ], $validated['attributes']);
        $setter->handle($product, $attributes);
        $this->audit($request, $audit, 'catalog.product_attributes.updated', $product, [
            'attribute_definition_ids' => array_column($attributes, 'attribute_definition_id'),
        ]);

        return back()->with('status', 'Atrybuty opisowe zostały zapisane.');
    }

    public function uploadImage(Request $request, Product $product, MediaLibrary $media, MediaAttachments $attachments, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(int) config('media.max_size_kb')],
        ]);
        $file = $request->file('image');
        $uploaded = $media->store(
            contents: $file->get(),
            originalFilename: $file->getClientOriginalName(),
            mimeType: (string) $file->getMimeType(),
            uploadedByStaffId: (string) $request->user()?->getAuthIdentifier(),
        );
        $attachments->attach('product', (int) $product->getKey(), 'gallery', $uploaded->id);
        $this->audit($request, $audit, 'catalog.product.image_uploaded', $product, ['media_id' => $uploaded->id]);

        return back()->with('status', 'Zdjęcie zostało dodane.');
    }

    public function removeImage(Request $request, Product $product, int $mediaId, MediaAttachments $attachments, AuditLogger $audit): RedirectResponse
    {
        $attachments->detach('product', (int) $product->getKey(), 'gallery', $mediaId);
        $this->audit($request, $audit, 'catalog.product.image_removed', $product, ['media_id' => $mediaId]);

        return back()->with('status', 'Zdjęcie zostało usunięte.');
    }

    public function reorderImages(Request $request, Product $product, MediaAttachments $attachments, AuditLogger $audit): RedirectResponse
    {
        /** @var array{media_ids:list<int>} $validated */
        $validated = $request->validate([
            'media_ids' => ['required', 'array'],
            'media_ids.*' => ['integer'],
        ]);
        $attachments->reorder('product', (int) $product->getKey(), 'gallery', array_map('intval', $validated['media_ids']));
        $this->audit($request, $audit, 'catalog.product.images_reordered', $product, []);

        return back()->with('status', 'Kolejność zdjęć została zapisana.');
    }

    public function uploadDigitalFile(Request $request, Product $product, ProductVariant $variant, DigitalFileManager $manager, AuditLogger $audit): RedirectResponse
    {
        abort_unless($variant->product_id === (int) $product->getKey() && $product->type === ProductType::Digital->value, 404);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:190'],
            'max_downloads' => ['nullable', 'integer', 'min:1'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:'.(int) config('media.private_max_size_kb')],
        ]);
        $file = $request->file('file');
        try {
            $created = $manager->createFile(
                variantId: (int) $variant->getKey(), label: (string) $validated['label'],
                maxDownloads: isset($validated['max_downloads']) ? (int) $validated['max_downloads'] : null,
                validityDays: isset($validated['validity_days']) ? (int) $validated['validity_days'] : null,
                sourcePath: (string) $file->getRealPath(), originalFilename: $file->getClientOriginalName(), mimeType: (string) $file->getMimeType(),
                uploadedByStaffId: (string) $request->user()?->getAuthIdentifier(),
            );
        } catch (\RuntimeException) {
            return back()->withErrors(['file' => 'Nie udało się zapisać pliku w magazynie plików. Spróbuj ponownie lub skontaktuj się z administratorem hostingu.']);
        }
        $this->audit($request, $audit, 'catalog.variant.digital_file_uploaded', $product, ['variant_id' => (int) $variant->getKey(), 'file_id' => $created->id]);

        return back()->with('status', 'Plik cyfrowy został dodany.');
    }

    public function uploadDigitalFileVersion(Request $request, Product $product, ProductVariant $variant, int $file, DigitalFileManager $manager, AuditLogger $audit): RedirectResponse
    {
        abort_unless($variant->product_id === (int) $product->getKey(), 404);
        $validated = $request->validate(['file' => ['required', 'file', 'max:'.(int) config('media.private_max_size_kb')]]);
        $uploaded = $validated['file'];
        try {
            $updated = $manager->uploadNewVersion(
                fileId: $file, sourcePath: (string) $uploaded->getRealPath(), originalFilename: $uploaded->getClientOriginalName(),
                mimeType: (string) $uploaded->getMimeType(), uploadedByStaffId: (string) $request->user()?->getAuthIdentifier(),
            );
        } catch (\RuntimeException) {
            return back()->withErrors(['file' => 'Nie udało się zapisać pliku w magazynie plików. Spróbuj ponownie lub skontaktuj się z administratorem hostingu.']);
        }
        $this->audit($request, $audit, 'catalog.variant.digital_file_version_uploaded', $product, ['variant_id' => (int) $variant->getKey(), 'file_id' => $updated->id]);

        return back()->with('status', 'Nowa wersja pliku została wgrana.');
    }

    public function updateDigitalFile(Request $request, Product $product, ProductVariant $variant, int $file, DigitalFileManager $manager, AuditLogger $audit): RedirectResponse
    {
        abort_unless($variant->product_id === (int) $product->getKey(), 404);
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:190'],
            'max_downloads' => ['nullable', 'integer', 'min:1'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ]);
        $manager->updateSettings(
            $file, (string) $validated['label'],
            isset($validated['max_downloads']) ? (int) $validated['max_downloads'] : null,
            isset($validated['validity_days']) ? (int) $validated['validity_days'] : null,
        );
        $manager->setActive($file, (bool) $validated['active']);
        $this->audit($request, $audit, 'catalog.variant.digital_file_updated', $product, ['variant_id' => (int) $variant->getKey(), 'file_id' => $file]);

        return back()->with('status', 'Plik cyfrowy został zapisany.');
    }

    public function reorderDigitalFiles(Request $request, Product $product, ProductVariant $variant, DigitalFileManager $manager, AuditLogger $audit): RedirectResponse
    {
        abort_unless($variant->product_id === (int) $product->getKey(), 404);
        /** @var array{file_ids:list<int>} $validated */
        $validated = $request->validate(['file_ids' => ['required', 'array'], 'file_ids.*' => ['integer']]);
        $manager->reorder((int) $variant->getKey(), array_map('intval', $validated['file_ids']));
        $this->audit($request, $audit, 'catalog.variant.digital_files_reordered', $product, ['variant_id' => (int) $variant->getKey()]);

        return back()->with('status', 'Kolejność plików została zapisana.');
    }

    public function searchRelationCandidates(Request $request, Product $product, LanguageRepository $languages): JsonResponse
    {
        $locale = $this->defaultLocale($languages);
        $term = trim((string) $request->query('q', ''));

        $query = Product::query()
            ->where('id', '!=', $product->getKey())
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

        $results = $query->orderByDesc('id')->limit(20)->get()
            ->map(static function (Product $candidate): array {
                $translation = $candidate->translations->first();
                $variant = $candidate->variants->first();

                return [
                    'id' => (int) $candidate->getKey(),
                    'name' => $translation === null ? 'Brak tłumaczenia' : $translation->name,
                    'sku' => $variant?->sku,
                    'status' => $candidate->status,
                ];
            })->values()->all();

        return response()->json(['data' => $results]);
    }

    public function setRelations(
        SetProductRelationsRequest $request,
        Product $product,
        SetProductRelations $setter,
        AuditLogger $audit,
    ): RedirectResponse {
        /** @var array{relations:list<array{type:string,target_product_id:int}>} $validated */
        $validated = $request->validated();
        $relations = array_map(static fn (array $relation): array => [
            'type' => (string) $relation['type'],
            'target_product_id' => (int) $relation['target_product_id'],
        ], $validated['relations']);
        $setter->handle($product, $relations);
        $this->audit($request, $audit, 'catalog.product_relations.updated', $product, ['count' => count($relations)]);

        return back()->with('status', 'Powiązane produkty zostały zapisane.');
    }

    public function update(
        UpdateProductRequest $request,
        Product $product,
        UpdateProduct $updater,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = ['type' => $product->type, 'status' => $product->status];
        $product = $updater->handle($product, $this->productData($request));

        $this->audit($request, $audit, 'catalog.product.updated', $product, [
            'before' => $before,
            'after' => ['type' => $product->type, 'status' => $product->status],
        ]);

        return back()->with('status', 'Produkt został zapisany.');
    }

    public function destroy(Request $request, Product $product, AuditLogger $audit): RedirectResponse
    {
        $previousStatus = $product->status;
        $product->update([
            'status' => ProductStatus::Archived->value,
            'archived_at' => now(),
        ]);

        $this->audit($request, $audit, 'catalog.product.archived', $product, [
            'before' => ['status' => $previousStatus],
            'after' => ['status' => ProductStatus::Archived->value],
        ]);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return redirect()->route('panel.catalog.products.index')->with('status', 'Produkt został zarchiwizowany.');
    }

    /** @return array<string, mixed> */
    private function formOptions(LanguageRepository $languages, CatalogSeoSettingsRepository $seoSettings): array
    {
        $defaultVariantSeoMode = $seoSettings->current()->defaultVariantSeoMode;
        $defaultVariantSeoModeLabel = match ($defaultVariantSeoMode) {
            VariantSeoMode::IndexedSeparately->value => 'Indeksuj warianty osobno',
            VariantSeoMode::Noindex->value => 'Nie indeksuj wariantów',
            default => 'Canonical do produktu głównego',
        };

        return [
            'defaultLocale' => $this->defaultLocale($languages),
            'languages' => array_map(static fn (LanguageData $language): array => [
                'code' => $language->code,
                'name' => $language->name,
            ], array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled))),
            'types' => $this->enumOptions(ProductType::cases()),
            'statuses' => $this->enumOptions(ProductStatus::cases()),
            // '' (not a real VariantSeoMode value) means "no override" — the
            // product inherits catalog_seo_settings.default_variant_seo_mode,
            // shown here so the merchant doesn't have to jump to /panel/seo to
            // know what "inherit" currently resolves to.
            'variantSeoModes' => [
                ['value' => '', 'label' => "Domyślne z ustawień SEO (obecnie: {$defaultVariantSeoModeLabel})"],
                ...$this->enumOptions(VariantSeoMode::cases()),
            ],
            'units' => $this->enumOptions(ProductUnit::cases()),
            'brands' => Brand::query()
                ->with(['translations' => fn ($query) => $query->where('locale', $this->defaultLocale($languages))])
                ->get()
                ->map(static function (Brand $brand): array {
                    $translation = $brand->translations->first();

                    return ['id' => (int) $brand->getKey(), 'name' => $translation === null ? 'Marka #'.$brand->getKey() : $translation->name];
                })->values()->all(),
            'categories' => Category::query()
                ->with(['translations' => fn ($query) => $query->where('locale', $this->defaultLocale($languages))])
                ->orderBy('parent_id')->orderBy('position')->orderBy('id')->get()
                ->map(static function (Category $category): array {
                    $translation = $category->translations->first();

                    return [
                        'id' => (int) $category->getKey(),
                        'parent_id' => $category->parent_id,
                        'name' => $translation === null ? 'Kategoria #'.$category->getKey() : $translation->name,
                    ];
                })->values()->all(),
            'variantAttributes' => AttributeDefinition::query()
                ->whereHas('values')
                ->with([
                    'translations' => fn ($query) => $query->where('locale', $this->defaultLocale($languages)),
                    'values.translations' => fn ($query) => $query->where('locale', $this->defaultLocale($languages)),
                ])->orderBy('key')->get()->map(static function (AttributeDefinition $definition): array {
                    $translation = $definition->translations->first();

                    return [
                        'id' => (int) $definition->getKey(),
                        'name' => $translation === null ? $definition->key : $translation->name,
                        'values' => $definition->values->map(static function (AttributeValue $value): array {
                            $translation = $value->translations->first();

                            return ['id' => (int) $value->getKey(), 'name' => $translation === null ? $value->value_key : $translation->name, 'color_hex' => $value->color_hex];
                        })->values()->all(),
                    ];
                })->values()->all(),
            'maxVariants' => (int) config('catalog.max_variants_per_product', 100),
        ];
    }

    public function store(StoreProductRequest $request, CreateProduct $creator, AuditLogger $audit): RedirectResponse
    {
        $product = $creator->handle($this->productData($request));

        $this->audit($request, $audit, 'catalog.product.created', $product, [
            'type' => $product->type,
            'status' => $product->status,
        ]);

        return redirect()->route('panel.catalog.products.index')->with('status', 'Produkt został utworzony.');
    }

    private function productData(StoreProductRequest $request): CreateProductData
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        return new CreateProductData(
            locale: (string) $validated['locale'],
            name: (string) $validated['name'],
            shortDescription: isset($validated['short_description']) ? (string) $validated['short_description'] : null,
            descriptionHtml: isset($validated['description_html']) ? (string) $validated['description_html'] : null,
            type: (string) $validated['type'],
            status: (string) $validated['status'],
            variantSeoMode: isset($validated['variant_seo_mode']) ? (string) $validated['variant_seo_mode'] : null,
            featured: (bool) $validated['featured'],
            unitCode: (string) $validated['unit_code'],
            minimumQuantity: (string) $validated['minimum_quantity'],
            maximumQuantity: isset($validated['maximum_quantity']) ? (string) $validated['maximum_quantity'] : null,
            quantityStep: (string) $validated['quantity_step'],
            sku: isset($validated['sku']) ? (string) $validated['sku'] : null,
            gtin: isset($validated['gtin']) ? (string) $validated['gtin'] : null,
            mpn: isset($validated['mpn']) ? (string) $validated['mpn'] : null,
            brandId: isset($validated['brand_id']) ? (int) $validated['brand_id'] : null,
            primaryCategoryId: isset($validated['primary_category_id']) ? (int) $validated['primary_category_id'] : null,
            categoryIds: array_map(static fn (mixed $id): int => (int) $id, $validated['category_ids']),
        );
    }

    private function defaultLocale(LanguageRepository $languages): string
    {
        $language = $languages->default();

        return $language === null ? (string) config('app.locale', 'pl') : $language->code;
    }

    /** @param array<string, mixed> $changes */
    private function audit(Request $request, AuditLogger $audit, string $action, Product $product, array $changes): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: 'product',
            subjectId: (string) $product->getKey(),
            changes: $changes,
        ));
    }

    /**
     * @param  list<ProductType|ProductStatus|ProductUnit|VariantSeoMode>  $cases
     * @return list<array{value:string,label:string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(static fn (ProductType|ProductStatus|ProductUnit|VariantSeoMode $case): array => [
            'value' => $case->value,
            'label' => match ($case->value) {
                'physical' => 'Fizyczny',
                'digital' => 'Cyfrowy',
                'draft' => 'Szkic',
                'published' => 'Opublikowany',
                'archived' => 'Zarchiwizowany',
                'piece' => 'Sztuka',
                'kilogram' => 'Kilogram',
                'meter' => 'Metr',
                'canonical_to_product' => 'Canonical do produktu głównego (domyślne)',
                'indexed_separately' => 'Indeksuj warianty osobno',
                'noindex' => 'Nie indeksuj wariantów',
            },
        ], $cases);
    }
}
