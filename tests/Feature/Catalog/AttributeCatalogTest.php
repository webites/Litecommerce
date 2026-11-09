<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class AttributeCatalogTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->staff = StaffAccount::factory()->create();
    }

    public function test_groups_are_created_renamed_ordered_and_deleted_without_losing_their_attributes(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-groups.store'), ['locale' => 'pl', 'key' => 'wymiary', 'name' => 'Wymiary'])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-groups.store'), ['locale' => 'pl', 'key' => 'wymiary', 'name' => 'Duplikat'])->assertSessionHasErrors('key');
        $groupId = (int) DB::table('attribute_groups')->where('key', 'wymiary')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'szerokosc', 'name' => 'Szerokość', 'type' => 'number', 'is_filterable' => false, 'attribute_group_id' => $groupId,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->put(route('panel.catalog.products.references.attribute-groups.update', $groupId), ['locale' => 'pl', 'name' => 'Rozmiary', 'position' => 5])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attribute_group_translations', ['attribute_group_id' => $groupId, 'name' => 'Rozmiary']);
        $this->assertSame(5, (int) DB::table('attribute_groups')->where('id', $groupId)->value('position'));

        $this->actingAs($this->staff)->get(route('panel.catalog.products.references.attributes.index'))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.name', 'Rozmiary')
                ->where('groups.0.attributes_count', 1)
                ->where('attributes.0.group_id', $groupId));

        $this->actingAs($this->staff)->delete(route('panel.catalog.products.references.attribute-groups.destroy', $groupId))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('attribute_groups', ['id' => $groupId]);
        $this->assertNull(DB::table('attribute_definitions')->where('key', 'szerokosc')->value('attribute_group_id'));
    }

    public function test_an_attribute_is_edited_with_the_slug_kept_and_deleted_with_its_product_values(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), ['locale' => 'pl', 'key' => 'material', 'name' => 'Materiał', 'type' => 'text', 'is_filterable' => false])->assertSessionHasNoErrors();
        $definitionId = (int) DB::table('attribute_definitions')->where('key', 'material')->value('id');
        $slug = (string) DB::table('attribute_definition_translations')->where('attribute_definition_id', $definitionId)->value('slug');

        $this->actingAs($this->staff)->put(route('panel.catalog.products.references.attributes.update', $definitionId), [
            'locale' => 'pl', 'name' => 'Materiał wykonania', 'attribute_group_id' => null, 'position' => 3, 'is_filterable' => true, 'visible_on_product' => false,
        ])->assertSessionHasNoErrors();

        $row = DB::table('attribute_definitions')->where('id', $definitionId)->first();
        $this->assertTrue((bool) $row->is_filterable);
        $this->assertFalse((bool) $row->visible_on_product);
        $this->assertSame(3, (int) $row->position);
        $this->assertDatabaseHas('attribute_definition_translations', ['attribute_definition_id' => $definitionId, 'name' => 'Materiał wykonania', 'slug' => $slug]);

        $this->actingAs($this->staff)->put(route('panel.catalog.products.references.attributes.update', $definitionId), [
            'locale' => 'pl', 'name' => 'X', 'attribute_group_id' => 999, 'position' => 0, 'is_filterable' => false, 'visible_on_product' => true,
        ])->assertSessionHasErrors('attribute_group_id');

        $this->actingAs($this->staff)->delete(route('panel.catalog.products.references.attributes.destroy', $definitionId))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('attribute_definitions', ['id' => $definitionId]);
    }

    public function test_an_attribute_and_a_value_used_as_variant_options_cannot_be_deleted(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Koszulka', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), ['locale' => 'pl', 'key' => 'rozmiar', 'name' => 'Rozmiar', 'type' => 'single_select', 'is_filterable' => true])->assertSessionHasNoErrors();
        $definitionId = (int) DB::table('attribute_definitions')->where('key', 'rozmiar')->value('id');
        foreach (['s' => 'S', 'm' => 'M'] as $key => $name) {
            $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-values.store', $definitionId), ['locale' => 'pl', 'value_key' => $key, 'name' => $name])->assertSessionHasNoErrors();
        }
        $small = (int) DB::table('attribute_values')->where('value_key', 's')->value('id');
        $medium = (int) DB::table('attribute_values')->where('value_key', 'm')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [['attribute_definition_id' => $definitionId, 'attribute_value_ids' => [$small]]],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->delete(route('panel.catalog.products.references.attributes.destroy', $definitionId))->assertSessionHasErrors('attribute');
        $this->actingAs($this->staff)->delete(route('panel.catalog.products.references.attribute-values.destroy', $small))->assertSessionHasErrors('value');
        $this->assertDatabaseHas('attribute_definitions', ['id' => $definitionId]);

        $this->actingAs($this->staff)->delete(route('panel.catalog.products.references.attribute-values.destroy', $medium))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('attribute_values', ['id' => $medium]);
    }

    public function test_a_value_is_renamed_and_recoloured_and_a_colour_value_needs_a_hex(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), ['locale' => 'pl', 'key' => 'kolor', 'name' => 'Kolor', 'type' => 'color', 'is_filterable' => true])->assertSessionHasNoErrors();
        $definitionId = (int) DB::table('attribute_definitions')->where('key', 'kolor')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-values.store', $definitionId), ['locale' => 'pl', 'value_key' => 'czerwony', 'name' => 'Czerwony', 'color_hex' => '#ff0000'])->assertSessionHasNoErrors();
        $valueId = (int) DB::table('attribute_values')->where('value_key', 'czerwony')->value('id');

        $this->actingAs($this->staff)->put(route('panel.catalog.products.references.attribute-values.update', $valueId), ['locale' => 'pl', 'name' => 'Karmazynowy', 'position' => 2, 'color_hex' => '#aa0000'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attribute_value_translations', ['attribute_value_id' => $valueId, 'name' => 'Karmazynowy']);
        $this->assertSame('#AA0000', DB::table('attribute_values')->where('id', $valueId)->value('color_hex'));

        $this->actingAs($this->staff)->put(route('panel.catalog.products.references.attribute-values.update', $valueId), ['locale' => 'pl', 'name' => 'Karmazynowy', 'position' => 2])->assertSessionHasErrors('color_hex');
    }

    public function test_the_product_form_receives_groups_and_each_attribute_group(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-groups.store'), ['locale' => 'pl', 'key' => 'dane', 'name' => 'Dane techniczne'])->assertSessionHasNoErrors();
        $groupId = (int) DB::table('attribute_groups')->where('key', 'dane')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), ['locale' => 'pl', 'key' => 'moc', 'name' => 'Moc', 'type' => 'number', 'is_filterable' => false, 'attribute_group_id' => $groupId])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Lampa', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');

        $this->actingAs($this->staff)->get(route('panel.catalog.products.edit', $productId))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('attributeGroups.0.name', 'Dane techniczne')
                ->where('productAttributes.0.group_id', $groupId));
    }

    public function test_only_catalog_managers_reach_the_attribute_catalog(): void
    {
        $editor = StaffAccount::factory()->create([
            'role' => SystemStaffRole::Administrator,
            'permission_overrides' => [CorePermission::ManageCatalog->value => false],
        ]);

        $this->actingAs($editor)->get(route('panel.catalog.products.references.attributes.index'))->assertForbidden();
        $this->actingAs($editor)->post(route('panel.catalog.products.references.attribute-groups.store'), ['locale' => 'pl', 'key' => 'x', 'name' => 'X'])->assertForbidden();
    }
}
