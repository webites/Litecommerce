<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ProductCreationTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl',
            'name' => 'Polski',
            'is_default' => true,
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_creates_product_with_one_default_variant_and_generated_sku(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)
            ->post(route('panel.catalog.products.store'), $this->payload())
            ->assertRedirect(route('panel.catalog.products.index'));

        $this->assertDatabaseHas('product_translations', ['locale' => 'pl', 'name' => 'Czerwona koszulka']);
        $this->assertDatabaseCount('product_variants', 1);
        $variant = DB::table('product_variants')->first();
        $this->assertNotNull($variant);
        $this->assertSame(1, (int) $variant->is_default);
        $this->assertMatchesRegularExpression('/^LC-[A-F0-9]{12}$/', (string) $variant->sku);
    }

    public function test_variant_seo_mode_is_null_when_not_submitted_meaning_inherit_the_store_wide_default(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['variant_seo_mode' => null]);
    }

    public function test_variant_seo_mode_is_saved_on_create_and_update(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload([
            'variant_seo_mode' => 'indexed_separately',
        ]))->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $this->assertDatabaseHas('products', ['id' => $productId, 'variant_seo_mode' => 'indexed_separately']);

        $this->actingAs($staff)->put(route('panel.catalog.products.update', $productId), $this->payload([
            'variant_seo_mode' => 'noindex',
        ]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $productId, 'variant_seo_mode' => 'noindex']);
    }

    public function test_it_normalizes_custom_sku_and_accepts_valid_ean(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload([
            'sku' => ' shirt-red-m ',
            'gtin' => '5901234123457',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', ['sku' => 'SHIRT-RED-M', 'gtin' => '5901234123457']);
    }

    public function test_it_rejects_invalid_gtin_and_invalid_digital_quantity(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->from(route('panel.catalog.products.create'))
            ->post(route('panel.catalog.products.store'), $this->payload([
                'type' => 'digital',
                'gtin' => '5901234123458',
                'maximum_quantity' => '2',
            ]))
            ->assertSessionHasErrors(['gtin', 'type']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_explicit_permission_denial_blocks_catalog(): void
    {
        $staff = StaffAccount::factory()->create([
            'role' => SystemStaffRole::Administrator,
            'permission_overrides' => [CorePermission::ManageCatalog->value => false],
        ]);

        $this->actingAs($staff)->get(route('panel.catalog.products.index'))->assertForbidden();
    }

    public function test_it_updates_product_translation_and_default_variant_atomically(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['sku' => 'OLD-SKU']));
        $productId = (int) DB::table('products')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.update', $productId), $this->payload([
            'name' => 'Koszulka po zmianie',
            'sku' => 'new-sku',
            'status' => 'published',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_translations', ['product_id' => $productId, 'name' => 'Koszulka po zmianie']);
        $this->assertDatabaseHas('product_variants', ['product_id' => $productId, 'sku' => 'NEW-SKU', 'is_default' => true]);
        $this->assertDatabaseHas('products', ['id' => $productId, 'status' => 'published']);
        $this->assertNotNull(DB::table('products')->where('id', $productId)->value('publish_at'));
    }

    public function test_archiving_preserves_product_variant_and_translation(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload());
        $productId = (int) DB::table('products')->value('id');

        $this->actingAs($staff)
            ->delete(route('panel.catalog.products.destroy', $productId))
            ->assertRedirect(route('panel.catalog.products.index'));

        $this->assertDatabaseHas('products', ['id' => $productId, 'status' => 'archived']);
        $this->assertNotNull(DB::table('products')->where('id', $productId)->value('archived_at'));
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('product_translations', 1);
        $this->assertDatabaseHas('audit_entries', ['action' => 'catalog.product.archived', 'subject_id' => (string) $productId]);
    }

    public function test_it_creates_shared_brand_and_category_tree_and_assigns_them_to_product(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.references.brands.store'), [
            'locale' => 'pl',
            'name' => 'Lite Brand',
        ])->assertSessionHasNoErrors();
        $brandId = (int) DB::table('brands')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl',
            'name' => 'Odzież',
            'parent_id' => null,
            'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $parentId = (int) DB::table('categories')->value('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl',
            'name' => 'Koszulki',
            'parent_id' => $parentId,
            'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('categories')->max('id');

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload([
            'brand_id' => $brandId,
            'category_ids' => [$parentId, $categoryId],
            'primary_category_id' => $categoryId,
        ]))->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->value('id');
        $this->assertDatabaseHas('products', ['id' => $productId, 'brand_id' => $brandId, 'primary_category_id' => $categoryId]);
        $this->assertDatabaseHas('product_categories', ['product_id' => $productId, 'category_id' => $parentId]);
        $this->assertDatabaseHas('product_categories', ['product_id' => $productId, 'category_id' => $categoryId]);
    }

    public function test_primary_category_must_be_assigned_to_product(): void
    {
        $staff = StaffAccount::factory()->create();
        $categoryId = (int) DB::table('categories')->insertGetId([
            'position' => 0,
            'visible_in_tree' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload([
            'category_ids' => [],
            'primary_category_id' => $categoryId,
        ]))->assertSessionHasErrors('primary_category_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_variant_generator_creates_only_missing_combinations_and_preserves_initial_sku(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['sku' => 'BASE-SKU']));
        $productId = (int) DB::table('products')->value('id');
        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'color', 'Kolor', ['red' => 'Czerwony', 'blue' => 'Niebieski']);
        [$sizeId, $sizeValues] = $this->createAttributeWithValues($staff, 'size', 'Rozmiar', ['s' => 'S', 'm' => 'M']);
        $options = [
            ['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues],
            ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => $sizeValues],
        ];

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), ['options' => $options])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('product_variants', 4);
        $this->assertDatabaseHas('product_variants', ['product_id' => $productId, 'sku' => 'BASE-SKU', 'is_default' => true]);
        $this->assertDatabaseCount('variant_option_values', 8);

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), ['options' => $options])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_variants', 4);
        $this->assertDatabaseCount('variant_option_values', 8);
    }

    public function test_variant_generator_rejects_value_from_another_attribute(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload());
        $productId = (int) DB::table('products')->value('id');
        [$colorId] = $this->createAttributeWithValues($staff, 'color', 'Kolor', ['red' => 'Czerwony']);
        [, $sizeValues] = $this->createAttributeWithValues($staff, 'size', 'Rozmiar', ['m' => 'M']);

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [['attribute_definition_id' => $colorId, 'attribute_value_ids' => $sizeValues]],
        ])->assertSessionHasErrors('options.0.attribute_value_ids');

        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_variant_generation_limit_is_enforced_before_writes(): void
    {
        config()->set('catalog.max_variants_per_product', 3);
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload());
        $productId = (int) DB::table('products')->value('id');
        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'color', 'Kolor', ['red' => 'Czerwony', 'blue' => 'Niebieski']);
        [$sizeId, $sizeValues] = $this->createAttributeWithValues($staff, 'size', 'Rozmiar', ['s' => 'S', 'm' => 'M']);

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [
                ['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues],
                ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => $sizeValues],
            ],
        ])->assertSessionHasErrors('options');

        $this->assertDatabaseCount('product_variants', 1);
        $this->assertDatabaseCount('product_options', 0);
    }

    public function test_staff_can_edit_and_disable_individual_variant(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['sku' => 'BEFORE']));
        $productId = (int) DB::table('products')->value('id');
        $variantId = (int) DB::table('product_variants')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.update', [$productId, $variantId]), [
            'locale' => 'pl',
            'sku' => ' variant-edited ',
            'gtin' => '5901234123457',
            'mpn' => 'MPN-1',
            'enabled' => false,
            'name' => 'Czerwony / M',
            'short_description' => 'Opis wariantu',
            'description_html' => '<p>Pełny opis wariantu</p>',
            'weight_grams' => '250',
            'length_mm' => '300',
            'width_mm' => '200',
            'height_mm' => '20',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'sku' => 'VARIANT-EDITED',
            'gtin' => '5901234123457',
            'mpn' => 'MPN-1',
            'enabled' => false,
            'weight_grams' => 250,
        ]);
        $this->assertDatabaseHas('variant_translations', [
            'product_variant_id' => $variantId,
            'locale' => 'pl',
            'name' => 'Czerwony / M',
        ]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'catalog.variant.updated', 'subject_id' => (string) $productId]);
    }

    public function test_variant_from_another_product_cannot_be_updated(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['sku' => 'FIRST']));
        $firstProductId = (int) DB::table('products')->value('id');
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload(['sku' => 'SECOND']));
        $secondVariantId = (int) DB::table('product_variants')->where('sku', 'SECOND')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.update', [$firstProductId, $secondVariantId]), [
            'locale' => 'pl',
            'sku' => 'HIJACKED',
            'enabled' => false,
        ])->assertNotFound();

        $this->assertDatabaseHas('product_variants', ['id' => $secondVariantId, 'sku' => 'SECOND', 'enabled' => true]);
    }

    public function test_product_descriptive_attributes_support_typed_and_dictionary_values(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload());
        $productId = (int) DB::table('products')->value('id');
        [$materialId] = $this->createAttributeWithValues($staff, 'material', 'Materiał', [], 'text');
        [$colorId, $colorValues] = $this->createAttributeWithValues($staff, 'color', 'Kolor', ['red' => 'Czerwony']);

        $this->actingAs($staff)->put(route('panel.catalog.products.attributes.update', $productId), [
            'attributes' => [
                ['attribute_definition_id' => $materialId, 'value_ids' => [], 'text_value' => 'Bawełna'],
                ['attribute_definition_id' => $colorId, 'value_ids' => $colorValues],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $productId, 'attribute_definition_id' => $materialId, 'text_value' => 'Bawełna']);
        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $productId, 'attribute_definition_id' => $colorId, 'attribute_value_id' => $colorValues[0]]);
    }

    public function test_product_attribute_rejects_dictionary_value_from_another_definition(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), $this->payload());
        $productId = (int) DB::table('products')->value('id');
        [$colorId] = $this->createAttributeWithValues($staff, 'color', 'Kolor', ['red' => 'Czerwony']);
        [, $sizeValues] = $this->createAttributeWithValues($staff, 'size', 'Rozmiar', ['m' => 'M']);

        $this->actingAs($staff)->put(route('panel.catalog.products.attributes.update', $productId), [
            'attributes' => [['attribute_definition_id' => $colorId, 'value_ids' => $sizeValues]],
        ])->assertSessionHasErrors('attributes.0.value_ids');

        $this->assertDatabaseCount('product_attribute_values', 0);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'locale' => 'pl',
            'name' => 'Czerwona koszulka',
            'short_description' => 'Koszulka testowa',
            'description_html' => '',
            'type' => 'physical',
            'status' => 'draft',
            'featured' => false,
            'unit_code' => 'piece',
            'minimum_quantity' => '1',
            'maximum_quantity' => '',
            'quantity_step' => '1',
            'sku' => '',
            'gtin' => '',
            'mpn' => '',
            'brand_id' => '',
            'category_ids' => [],
            'primary_category_id' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $values
     * @return array{int, list<int>}
     */
    private function createAttributeWithValues(StaffAccount $staff, string $key, string $name, array $values, string $type = 'single_select'): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl',
            'key' => $key,
            'name' => $name,
            'type' => $type,
            'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $definitionId = (int) DB::table('attribute_definitions')->where('key', $key)->value('id');
        $ids = [];

        foreach ($values as $valueKey => $valueName) {
            $this->actingAs($staff)->post(route('panel.catalog.products.references.attribute-values.store', $definitionId), [
                'locale' => 'pl',
                'value_key' => $valueKey,
                'name' => $valueName,
            ])->assertSessionHasNoErrors();
            $ids[] = (int) DB::table('attribute_values')->where('attribute_definition_id', $definitionId)->where('value_key', $valueKey)->value('id');
        }

        return [$definitionId, $ids];
    }
}
