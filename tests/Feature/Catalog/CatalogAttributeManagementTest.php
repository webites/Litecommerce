<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Attribute/value slugs feed the variant URL path segments
 * (/products/{slug}/{axis-slug}/{value-slug}/...) — same SlugGenerator,
 * same "generated once per locale, global uniqueness" rule as
 * ProductSlugTest/product/category/brand, just applied to
 * attribute_definition_translations/attribute_value_translations. See
 * docs/knowledge-base/09-presentation.md's P1-014 URL-variant section.
 */
final class CatalogAttributeManagementTest extends TestCase
{
    use UsesIsolatedStorage;

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
    }

    public function test_attribute_definition_slug_is_generated_from_the_name(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'kolor', 'name' => 'Kolor', 'type' => 'color', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();

        $definitionId = (int) DB::table('attribute_definitions')->where('key', 'kolor')->value('id');
        $this->assertDatabaseHas('attribute_definition_translations', [
            'attribute_definition_id' => $definitionId, 'locale' => 'pl', 'slug' => 'kolor',
        ]);
    }

    public function test_attribute_value_slug_is_generated_from_the_name(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'kolor', 'name' => 'Kolor', 'type' => 'color', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $definitionId = (int) DB::table('attribute_definitions')->where('key', 'kolor')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.attribute-values.store', $definitionId), [
            'locale' => 'pl', 'value_key' => 'czerwony', 'name' => 'Czerwony', 'color_hex' => '#FF0000',
        ])->assertSessionHasNoErrors();

        $valueId = (int) DB::table('attribute_values')->where('attribute_definition_id', $definitionId)->where('value_key', 'czerwony')->value('id');
        $this->assertDatabaseHas('attribute_value_translations', [
            'attribute_value_id' => $valueId, 'locale' => 'pl', 'slug' => 'czerwony',
        ]);
    }

    /**
     * Attribute value slugs are unique globally per locale, not scoped to
     * one attribute definition — deliberately simpler than a per-attribute
     * scope (see 09-presentation.md), since URL resolution matches slugs
     * within one product's own axes, never across the whole catalog. Two
     * unrelated axes ("Rozmiar" and "Dopasowanie") both offering a value
     * named "M" is the rare case this suffix trades off against.
     */
    public function test_two_attribute_values_in_different_axes_with_the_same_name_get_a_disambiguating_slug_suffix(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'rozmiar', 'name' => 'Rozmiar', 'type' => 'single_select', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $sizeId = (int) DB::table('attribute_definitions')->where('key', 'rozmiar')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'dopasowanie', 'name' => 'Dopasowanie', 'type' => 'single_select', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $fitId = (int) DB::table('attribute_definitions')->where('key', 'dopasowanie')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.attribute-values.store', $sizeId), [
            'locale' => 'pl', 'value_key' => 'm', 'name' => 'M',
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.references.attribute-values.store', $fitId), [
            'locale' => 'pl', 'value_key' => 'm', 'name' => 'M',
        ])->assertSessionHasNoErrors();

        $sizeValueId = (int) DB::table('attribute_values')->where('attribute_definition_id', $sizeId)->where('value_key', 'm')->value('id');
        $fitValueId = (int) DB::table('attribute_values')->where('attribute_definition_id', $fitId)->where('value_key', 'm')->value('id');

        $this->assertDatabaseHas('attribute_value_translations', ['attribute_value_id' => $sizeValueId, 'slug' => 'm']);
        $this->assertDatabaseHas('attribute_value_translations', ['attribute_value_id' => $fitValueId, 'slug' => 'm-2']);
    }
}
