<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Events\CommittedEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Litecommerce\Modules\Audit\Contracts\AuditEntryData;
use Litecommerce\Modules\Audit\Contracts\AuditLogger;
use Litecommerce\Modules\Catalog\Application\SlugGenerator;
use Litecommerce\Modules\Catalog\Contracts\AttributeType;
use Litecommerce\Modules\Catalog\Contracts\Events\CatalogContentChanged;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeDefinition;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeDefinitionTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValue;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValueTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Brand;
use Litecommerce\Modules\Catalog\Infrastructure\Models\BrandTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\Category;
use Litecommerce\Modules\Catalog\Infrastructure\Models\CategoryTranslation;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\LanguageData;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class CatalogReferenceController extends Controller
{
    public function index(LanguageRepository $languages, MediaLibrary $media): Response
    {
        $defaultLanguage = $languages->default();
        $locale = $defaultLanguage === null ? (string) config('app.locale', 'pl') : $defaultLanguage->code;

        $brands = Brand::query()->with(['translations' => fn ($query) => $query->where('locale', $locale)])->latest('id')->get();
        $categories = Category::query()->with(['translations' => fn ($query) => $query->where('locale', $locale)])
            ->orderBy('parent_id')->orderBy('position')->orderBy('id')->get();
        $logos = $media->findMany($brands->pluck('logo_media_id')->filter()->map(fn (mixed $id): int => (int) $id)->values()->all());
        $images = $media->findMany($categories->pluck('image_media_id')->filter()->map(fn (mixed $id): int => (int) $id)->values()->all());

        return Inertia::render('Panel/Catalog/References', [
            'locale' => $locale,
            'languages' => array_map(static fn (LanguageData $language): array => [
                'code' => $language->code,
                'name' => $language->name,
            ], array_values(array_filter($languages->all(), static fn (LanguageData $language): bool => $language->isEnabled))),
            'brands' => $brands
                ->map(static function (Brand $brand) use ($logos): array {
                    $translation = $brand->translations->first();
                    $logo = $brand->logo_media_id === null ? null : ($logos[$brand->logo_media_id] ?? null);

                    return [
                        'id' => (int) $brand->getKey(),
                        'name' => $translation === null ? trans('catalog::labels.no_translation') : $translation->name,
                        'description_html' => $translation === null ? '' : ($translation->description_html ?? ''),
                        'logo_url' => $logo?->url,
                    ];
                })->all(),
            'categories' => $categories
                ->map(static function (Category $category) use ($images): array {
                    $translation = $category->translations->first();
                    $image = $category->image_media_id === null ? null : ($images[$category->image_media_id] ?? null);

                    return [
                        'id' => (int) $category->getKey(),
                        'parent_id' => $category->parent_id,
                        'position' => $category->position,
                        'name' => $translation === null ? trans('catalog::labels.no_translation') : $translation->name,
                        'visible_in_tree' => $category->visible_in_tree,
                        'image_url' => $image?->url,
                    ];
                })->all(),
        ]);
    }

    public function storeBrand(Request $request, AuditLogger $audit, SlugGenerator $slugGenerator, HtmlContentFilter $htmlFilter): RedirectResponse
    {
        /** @var array{locale:string,name:string,description_html?:string|null} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255', Rule::unique('brand_translations', 'name')->where('locale', $request->string('locale')->toString())],
            'description_html' => ['nullable', 'string', 'max:1000000'],
        ]);

        $brand = DB::transaction(function () use ($data, $slugGenerator, $htmlFilter): Brand {
            $brand = Brand::query()->create();
            $name = trim($data['name']);
            BrandTranslation::query()->create([
                'brand_id' => $brand->getKey(),
                'locale' => $data['locale'],
                'name' => $name,
                'slug' => $slugGenerator->generate($name, $data['locale'], BrandTranslation::class, 'marka'),
                'description_html' => $htmlFilter->clean($data['description_html'] ?? null),
            ]);

            return $brand;
        });
        $this->audit($request, $audit, 'catalog.brand.created', 'brand', (string) $brand->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.brand_created'));
    }

    public function updateBrand(Request $request, Brand $brand, AuditLogger $audit, SlugGenerator $slugGenerator, HtmlContentFilter $htmlFilter): RedirectResponse
    {
        $existingTranslationId = $brand->translations()->where('locale', $request->input('locale'))->value('id');
        /** @var array{locale:string,name:string,description_html?:string|null} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('brand_translations', 'name')->where('locale', $request->string('locale')->toString())->ignore($existingTranslationId),
            ],
            'description_html' => ['nullable', 'string', 'max:1000000'],
        ]);

        // A slug is never regenerated on rename (see SlugGenerator's docblock) —
        // only a brand-new locale translation gets one assigned here.
        $existingSlug = $existingTranslationId === null ? null : BrandTranslation::query()->find($existingTranslationId)?->slug;
        $name = trim($data['name']);

        BrandTranslation::query()->updateOrCreate(
            ['brand_id' => $brand->getKey(), 'locale' => $data['locale']],
            [
                'name' => $name,
                'slug' => $existingSlug ?? $slugGenerator->generate($name, $data['locale'], BrandTranslation::class, 'marka'),
                'description_html' => $htmlFilter->clean($data['description_html'] ?? null),
            ],
        );
        $this->audit($request, $audit, 'catalog.brand.updated', 'brand', (string) $brand->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.brand_saved'));
    }

    public function uploadBrandLogo(Request $request, Brand $brand, MediaLibrary $media, AuditLogger $audit): RedirectResponse
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
        $brand->update(['logo_media_id' => $uploaded->id]);
        $this->audit($request, $audit, 'catalog.brand.logo_uploaded', 'brand', (string) $brand->getKey());

        return back()->with('status', trans('catalog::flash.brand_logo_saved'));
    }

    public function removeBrandLogo(Request $request, Brand $brand, AuditLogger $audit): RedirectResponse
    {
        $brand->update(['logo_media_id' => null]);
        $this->audit($request, $audit, 'catalog.brand.logo_removed', 'brand', (string) $brand->getKey());

        return back()->with('status', trans('catalog::flash.brand_logo_removed'));
    }

    public function storeCategory(Request $request, AuditLogger $audit, SlugGenerator $slugGenerator): RedirectResponse
    {
        /** @var array{locale:string,name:string,parent_id?:int|null,visible_in_tree:bool} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'visible_in_tree' => ['required', 'boolean'],
        ]);

        $category = DB::transaction(function () use ($data, $slugGenerator): Category {
            $category = Category::query()->create([
                'parent_id' => $data['parent_id'] ?? null,
                'visible_in_tree' => $data['visible_in_tree'],
            ]);
            $name = trim($data['name']);
            CategoryTranslation::query()->create([
                'category_id' => $category->getKey(),
                'locale' => $data['locale'],
                'name' => $name,
                'slug' => $slugGenerator->generate($name, $data['locale'], CategoryTranslation::class, 'kategoria'),
            ]);

            return $category;
        });
        $this->audit($request, $audit, 'catalog.category.created', 'category', (string) $category->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.category_created'));
    }

    public function updateCategory(Request $request, Category $category, AuditLogger $audit, SlugGenerator $slugGenerator): RedirectResponse
    {
        /** @var array{locale:string,name:string,parent_id?:int|null,position:int,visible_in_tree:bool} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', Rule::notIn([$category->getKey()])],
            'position' => ['required', 'integer', 'min:0'],
            'visible_in_tree' => ['required', 'boolean'],
        ]);

        if ($this->parentWouldCreateCycle($category, $data['parent_id'] ?? null)) {
            throw ValidationException::withMessages(['parent_id' => trans('catalog::validation.category_self_parent')]);
        }

        DB::transaction(function () use ($category, $data, $slugGenerator): void {
            $category->update([
                'parent_id' => $data['parent_id'] ?? null,
                'position' => $data['position'],
                'visible_in_tree' => $data['visible_in_tree'],
            ]);
            $existingSlug = $category->translations()->where('locale', $data['locale'])->value('slug');
            $name = trim($data['name']);
            CategoryTranslation::query()->updateOrCreate(
                ['category_id' => $category->getKey(), 'locale' => $data['locale']],
                ['name' => $name, 'slug' => $existingSlug ?? $slugGenerator->generate($name, $data['locale'], CategoryTranslation::class, 'kategoria')],
            );
        });
        $this->audit($request, $audit, 'catalog.category.updated', 'category', (string) $category->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.category_saved'));
    }

    public function uploadCategoryImage(Request $request, Category $category, MediaLibrary $media, AuditLogger $audit): RedirectResponse
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
        $category->update(['image_media_id' => $uploaded->id]);
        $this->audit($request, $audit, 'catalog.category.image_uploaded', 'category', (string) $category->getKey());

        return back()->with('status', trans('catalog::flash.category_image_saved'));
    }

    public function removeCategoryImage(Request $request, Category $category, AuditLogger $audit): RedirectResponse
    {
        $category->update(['image_media_id' => null]);
        $this->audit($request, $audit, 'catalog.category.image_removed', 'category', (string) $category->getKey());

        return back()->with('status', trans('catalog::flash.category_image_removed'));
    }

    private function parentWouldCreateCycle(Category $category, ?int $newParentId): bool
    {
        if ($newParentId === null) {
            return false;
        }

        $cursor = Category::query()->find($newParentId);
        $guard = 0;

        while ($cursor !== null && $guard++ < 100) {
            if ((int) $cursor->getKey() === (int) $category->getKey()) {
                return true;
            }

            $cursor = $cursor->parent_id === null ? null : Category::query()->find($cursor->parent_id);
        }

        return false;
    }

    public function storeAttribute(Request $request, AuditLogger $audit, SlugGenerator $slugGenerator): RedirectResponse
    {
        /** @var array{locale:string,key:string,name:string,type:string,is_filterable:bool,attribute_group_id?:int|null,visible_on_product?:bool} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:attribute_definitions,key'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'is_filterable' => ['required', 'boolean'],
            'attribute_group_id' => ['nullable', 'integer', 'exists:attribute_groups,id'],
            'visible_on_product' => ['sometimes', 'boolean'],
        ]);

        $definition = DB::transaction(function () use ($data, $slugGenerator): AttributeDefinition {
            $definition = AttributeDefinition::query()->create([
                'key' => $data['key'],
                'type' => $data['type'],
                'is_filterable' => $data['is_filterable'],
                'attribute_group_id' => $data['attribute_group_id'] ?? null,
                'visible_on_product' => $data['visible_on_product'] ?? true,
                'position' => (int) AttributeDefinition::query()->max('position') + 1,
            ]);
            $name = trim($data['name']);
            AttributeDefinitionTranslation::query()->create([
                'attribute_definition_id' => $definition->getKey(),
                'locale' => $data['locale'],
                'name' => $name,
                'slug' => $slugGenerator->generate($name, $data['locale'], AttributeDefinitionTranslation::class, $data['key']),
            ]);

            return $definition;
        });
        $this->audit($request, $audit, 'catalog.attribute.created', 'attribute_definition', (string) $definition->getKey());

        return back()->with('status', trans('catalog::flash.attribute_created'));
    }

    public function storeAttributeValue(
        Request $request,
        AttributeDefinition $attributeDefinition,
        AuditLogger $audit,
        SlugGenerator $slugGenerator,
    ): RedirectResponse {
        /** @var array{locale:string,value_key:string,name:string,color_hex?:string|null} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'value_key' => [
                'required', 'string', 'max:96', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('attribute_values', 'value_key')->where('attribute_definition_id', $attributeDefinition->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'color_hex' => [
                $attributeDefinition->type === AttributeType::Color->value ? 'required' : 'nullable',
                'string', 'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
        ]);

        $value = DB::transaction(function () use ($data, $attributeDefinition, $slugGenerator): AttributeValue {
            $value = AttributeValue::query()->create([
                'attribute_definition_id' => $attributeDefinition->getKey(),
                'value_key' => $data['value_key'],
                'color_hex' => isset($data['color_hex']) ? strtoupper($data['color_hex']) : null,
                'position' => $attributeDefinition->values()->count(),
            ]);
            $name = trim($data['name']);
            AttributeValueTranslation::query()->create([
                'attribute_value_id' => $value->getKey(),
                'locale' => $data['locale'],
                'name' => $name,
                'slug' => $slugGenerator->generate($name, $data['locale'], AttributeValueTranslation::class, $data['value_key']),
            ]);

            return $value;
        });
        $this->audit($request, $audit, 'catalog.attribute_value.created', 'attribute_value', (string) $value->getKey());

        return back()->with('status', trans('catalog::flash.attribute_value_created'));
    }

    private function audit(Request $request, AuditLogger $audit, string $action, string $subjectType, string $subjectId): void
    {
        $audit->record(new AuditEntryData(
            actorType: 'staff',
            actorId: (string) $request->user()?->getAuthIdentifier(),
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            changes: [],
        ));
    }
}
