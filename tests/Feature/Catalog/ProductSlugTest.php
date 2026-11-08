<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductCatalog;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * P1-013's URL foundation: a product gets a unique-per-locale slug once,
 * at creation, never regenerated on a later name edit — see "Zakres i
 * świadome cięcia" (docs/knowledge-base/09-presentation.md#projekt-techniczny-p1-013).
 */
final class ProductSlugTest extends TestCase
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

    private function createProduct(StaffAccount $staff, string $name): int
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('products')->latest('id')->value('id');
    }

    public function test_slug_is_generated_from_the_product_name(): void
    {
        $staff = StaffAccount::factory()->create();
        $productId = $this->createProduct($staff, 'Piekny Kubek');

        $this->assertDatabaseHas('product_translations', ['product_id' => $productId, 'slug' => 'piekny-kubek']);
    }

    public function test_duplicate_names_in_the_same_locale_get_a_unique_suffixed_slug(): void
    {
        $staff = StaffAccount::factory()->create();
        $firstId = $this->createProduct($staff, 'Ten Sam Produkt');
        $secondId = $this->createProduct($staff, 'Ten Sam Produkt');
        $thirdId = $this->createProduct($staff, 'Ten Sam Produkt');

        $this->assertDatabaseHas('product_translations', ['product_id' => $firstId, 'slug' => 'ten-sam-produkt']);
        $this->assertDatabaseHas('product_translations', ['product_id' => $secondId, 'slug' => 'ten-sam-produkt-2']);
        $this->assertDatabaseHas('product_translations', ['product_id' => $thirdId, 'slug' => 'ten-sam-produkt-3']);
    }

    public function test_slug_is_not_regenerated_when_the_name_is_later_edited(): void
    {
        $staff = StaffAccount::factory()->create();
        $productId = $this->createProduct($staff, 'Nazwa Poczatkowa');

        $this->actingAs($staff)->put(route('panel.catalog.products.update', $productId), [
            'locale' => 'pl', 'name' => 'Zupelnie Inna Nazwa', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_translations', [
            'product_id' => $productId, 'name' => 'Zupelnie Inna Nazwa', 'slug' => 'nazwa-poczatkowa',
        ]);
    }

    public function test_product_catalog_excludes_drafts_and_finds_by_slug(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->createProduct($staff, 'Opublikowany Produkt');

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Szkic Produktu', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $catalog = app(ProductCatalog::class);

        $this->assertNotNull($catalog->findBySlug('opublikowany-produkt', 'pl'));
        $this->assertNull($catalog->findBySlug('szkic-produktu', 'pl'));

        $listing = $catalog->paginatePublished('pl', null, 20, 1);
        $this->assertSame(1, $listing['total']);
        $this->assertSame('opublikowany-produkt', $listing['items'][0]->slug);
    }
}
