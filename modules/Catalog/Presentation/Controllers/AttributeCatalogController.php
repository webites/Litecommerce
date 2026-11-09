<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Presentation\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Events\CommittedEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeGroup;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeGroupTranslation;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValue;
use Litecommerce\Modules\Catalog\Infrastructure\Models\AttributeValueTranslation;
use Litecommerce\Modules\Store\Contracts\LanguageRepository;

final class AttributeCatalogController extends Controller
{
    public function index(LanguageRepository $languages): Response
    {
        $locale = $this->locale($languages);
        $productCounts = DB::table('product_attribute_values')
            ->select('attribute_definition_id', DB::raw('count(distinct product_id) as products'))
            ->groupBy('attribute_definition_id')->pluck('products', 'attribute_definition_id');
        $optionDefinitionIds = DB::table('product_options')->distinct()->pluck('attribute_definition_id')->all();
        $optionValueIds = DB::table('product_option_values')->distinct()->pluck('attribute_value_id')->all();

        return Inertia::render('Panel/Catalog/Attributes', [
            'locale' => $locale,
            'attributeTypes' => array_map(static fn (AttributeType $type): array => [
                'value' => $type->value,
                'label' => trans('catalog::labels.attribute_type_'.$type->value),
            ], AttributeType::cases()),
            'groups' => AttributeGroup::query()
                ->with(['translations' => fn ($query) => $query->where('locale', $locale)])
                ->withCount('definitions')
                ->orderBy('position')->orderBy('id')->get()
                ->map(static fn (AttributeGroup $group): array => [
                    'id' => (int) $group->getKey(),
                    'key' => $group->key,
                    'name' => $group->translations->first()->name ?? $group->key,
                    'position' => $group->position,
                    'attributes_count' => (int) $group->getAttribute('definitions_count'),
                ])->values()->all(),
            'attributes' => AttributeDefinition::query()
                ->with([
                    'translations' => fn ($query) => $query->where('locale', $locale),
                    'values.translations' => fn ($query) => $query->where('locale', $locale),
                ])->orderBy('position')->orderBy('key')->get()
                ->map(static fn (AttributeDefinition $definition): array => [
                    'id' => (int) $definition->getKey(),
                    'key' => $definition->key,
                    'name' => $definition->translations->first()->name ?? $definition->key,
                    'type' => $definition->type,
                    'group_id' => $definition->attribute_group_id,
                    'position' => $definition->position,
                    'is_filterable' => $definition->is_filterable,
                    'visible_on_product' => $definition->visible_on_product,
                    'products_count' => (int) ($productCounts[$definition->getKey()] ?? 0),
                    'used_as_option' => in_array($definition->getKey(), $optionDefinitionIds, true),
                    'values' => $definition->values->sortBy('position')->map(static fn (AttributeValue $value): array => [
                        'id' => (int) $value->getKey(),
                        'key' => $value->value_key,
                        'name' => $value->translations->first()->name ?? $value->value_key,
                        'color_hex' => $value->color_hex,
                        'position' => $value->position,
                        'used_as_option' => in_array($value->getKey(), $optionValueIds, true),
                    ])->values()->all(),
                ])->values()->all(),
        ]);
    }

    public function storeGroup(Request $request, AuditLogger $audit): RedirectResponse
    {
        /** @var array{locale:string,key:string,name:string,position?:int|null} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:attribute_groups,key'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $group = DB::transaction(function () use ($data): AttributeGroup {
            $group = AttributeGroup::query()->create([
                'key' => $data['key'],
                'position' => $data['position'] ?? (int) AttributeGroup::query()->max('position') + 1,
            ]);
            AttributeGroupTranslation::query()->create([
                'attribute_group_id' => $group->getKey(), 'locale' => $data['locale'], 'name' => trim($data['name']),
            ]);

            return $group;
        });
        $this->audit($request, $audit, 'catalog.attribute_group.created', 'attribute_group', (string) $group->getKey());

        return back()->with('status', trans('catalog::flash.attribute_group_created'));
    }

    public function updateGroup(Request $request, AttributeGroup $attributeGroup, AuditLogger $audit): RedirectResponse
    {
        /** @var array{locale:string,name:string,position:int} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        DB::transaction(function () use ($data, $attributeGroup): void {
            $attributeGroup->update(['position' => $data['position']]);
            AttributeGroupTranslation::query()->updateOrCreate(
                ['attribute_group_id' => $attributeGroup->getKey(), 'locale' => $data['locale']],
                ['name' => trim($data['name'])],
            );
        });
        $this->audit($request, $audit, 'catalog.attribute_group.updated', 'attribute_group', (string) $attributeGroup->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.attribute_group_saved'));
    }

    public function destroyGroup(Request $request, AttributeGroup $attributeGroup, AuditLogger $audit): RedirectResponse
    {
        $id = (string) $attributeGroup->getKey();
        $attributeGroup->delete();
        $this->audit($request, $audit, 'catalog.attribute_group.deleted', 'attribute_group', $id);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.attribute_group_deleted'));
    }

    public function updateAttribute(Request $request, AttributeDefinition $attributeDefinition, AuditLogger $audit, SlugGenerator $slugGenerator): RedirectResponse
    {
        /** @var array{locale:string,name:string,attribute_group_id?:int|null,position:int,is_filterable:bool,visible_on_product:bool} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'attribute_group_id' => ['nullable', 'integer', 'exists:attribute_groups,id'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_filterable' => ['required', 'boolean'],
            'visible_on_product' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $attributeDefinition, $slugGenerator): void {
            $attributeDefinition->update([
                'attribute_group_id' => $data['attribute_group_id'] ?? null,
                'position' => $data['position'],
                'is_filterable' => $data['is_filterable'],
                'visible_on_product' => $data['visible_on_product'],
            ]);
            $name = trim($data['name']);
            $existing = AttributeDefinitionTranslation::query()
                ->where('attribute_definition_id', $attributeDefinition->getKey())->where('locale', $data['locale'])->first();
            AttributeDefinitionTranslation::query()->updateOrCreate(
                ['attribute_definition_id' => $attributeDefinition->getKey(), 'locale' => $data['locale']],
                ['name' => $name, 'slug' => $existing === null ? $slugGenerator->generate($name, $data['locale'], AttributeDefinitionTranslation::class, $attributeDefinition->key) : $existing->slug],
            );
        });
        $this->audit($request, $audit, 'catalog.attribute.updated', 'attribute_definition', (string) $attributeDefinition->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.attribute_saved'));
    }

    public function destroyAttribute(Request $request, AttributeDefinition $attributeDefinition, AuditLogger $audit): RedirectResponse
    {
        if (DB::table('product_options')->where('attribute_definition_id', $attributeDefinition->getKey())->exists()) {
            throw ValidationException::withMessages(['attribute' => trans('catalog::validation.attribute_in_use_as_option')]);
        }

        $id = (string) $attributeDefinition->getKey();
        $attributeDefinition->delete();
        $this->audit($request, $audit, 'catalog.attribute.deleted', 'attribute_definition', $id);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.attribute_deleted'));
    }

    public function updateValue(Request $request, AttributeValue $attributeValue, AuditLogger $audit, SlugGenerator $slugGenerator): RedirectResponse
    {
        $isColor = $attributeValue->definition()->value('type') === AttributeType::Color->value;
        /** @var array{locale:string,name:string,position:int,color_hex?:string|null} $data */
        $data = $request->validate([
            'locale' => ['required', 'string', 'exists:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'integer', 'min:0', 'max:100000'],
            'color_hex' => [$isColor ? 'required' : 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        DB::transaction(function () use ($data, $attributeValue, $isColor, $slugGenerator): void {
            $attributeValue->update([
                'position' => $data['position'],
                'color_hex' => $isColor && isset($data['color_hex']) ? strtoupper($data['color_hex']) : null,
            ]);
            $name = trim($data['name']);
            $existing = AttributeValueTranslation::query()
                ->where('attribute_value_id', $attributeValue->getKey())->where('locale', $data['locale'])->first();
            AttributeValueTranslation::query()->updateOrCreate(
                ['attribute_value_id' => $attributeValue->getKey(), 'locale' => $data['locale']],
                ['name' => $name, 'slug' => $existing === null ? $slugGenerator->generate($name, $data['locale'], AttributeValueTranslation::class, $attributeValue->value_key) : $existing->slug],
            );
        });
        $this->audit($request, $audit, 'catalog.attribute_value.updated', 'attribute_value', (string) $attributeValue->getKey());
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.attribute_value_saved'));
    }

    public function destroyValue(Request $request, AttributeValue $attributeValue, AuditLogger $audit): RedirectResponse
    {
        if (DB::table('product_option_values')->where('attribute_value_id', $attributeValue->getKey())->exists()) {
            throw ValidationException::withMessages(['value' => trans('catalog::validation.attribute_value_in_use_as_option')]);
        }

        $id = (string) $attributeValue->getKey();
        $attributeValue->delete();
        $this->audit($request, $audit, 'catalog.attribute_value.deleted', 'attribute_value', $id);
        DB::afterCommit(fn () => CommittedEvent::dispatch(new CatalogContentChanged));

        return back()->with('status', trans('catalog::flash.attribute_value_deleted'));
    }

    private function locale(LanguageRepository $languages): string
    {
        $default = $languages->default();

        return $default === null ? (string) config('app.locale', 'pl') : $default->code;
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
