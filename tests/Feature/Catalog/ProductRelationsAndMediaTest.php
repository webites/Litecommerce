<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ProductRelationsAndMediaTest extends TestCase
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

    public function test_staff_can_upload_reorder_and_remove_product_gallery_images(): void
    {
        $staff = StaffAccount::factory()->create();
        $productId = $this->createProduct($staff, 'Produkt z galerią');

        $this->actingAs($staff)->post(route('panel.catalog.products.images.store', $productId), [
            'image' => UploadedFile::fake()->image('first.png', 50, 50),
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.images.store', $productId), [
            'image' => UploadedFile::fake()->image('second.png', 50, 50),
        ])->assertSessionHasNoErrors();

        $attachments = DB::table('media_attachments')
            ->where('owner_type', 'product')->where('owner_id', $productId)->where('collection', 'gallery')
            ->orderBy('position')->get();
        $this->assertCount(2, $attachments);
        [$firstMediaId, $secondMediaId] = $attachments->pluck('media_id')->all();

        $this->actingAs($staff)->put(route('panel.catalog.products.images.reorder', $productId), [
            'media_ids' => [$secondMediaId, $firstMediaId],
        ])->assertSessionHasNoErrors();

        $reordered = DB::table('media_attachments')
            ->where('owner_type', 'product')->where('owner_id', $productId)->where('collection', 'gallery')
            ->orderBy('position')->pluck('media_id')->all();
        $this->assertSame([$secondMediaId, $firstMediaId], $reordered);

        $this->actingAs($staff)->delete(route('panel.catalog.products.images.destroy', [$productId, $firstMediaId]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('media_attachments', 1);
        $this->assertDatabaseHas('media', ['id' => $firstMediaId]);
    }

    public function test_staff_can_set_upsell_and_cross_sell_relations(): void
    {
        $staff = StaffAccount::factory()->create();
        $sourceId = $this->createProduct($staff, 'Produkt główny');
        $upsellTargetId = $this->createProduct($staff, 'Droższy zamiennik', 'UPSELL-SKU');
        $crossSellTargetId = $this->createProduct($staff, 'Akcesorium', 'XSELL-SKU');

        $this->actingAs($staff)->put(route('panel.catalog.products.relations.update', $sourceId), [
            'relations' => [
                ['type' => 'upsell', 'target_product_id' => $upsellTargetId],
                ['type' => 'cross_sell', 'target_product_id' => $crossSellTargetId],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_relations', ['source_product_id' => $sourceId, 'type' => 'upsell', 'target_product_id' => $upsellTargetId]);
        $this->assertDatabaseHas('product_relations', ['source_product_id' => $sourceId, 'type' => 'cross_sell', 'target_product_id' => $crossSellTargetId]);
        $this->assertDatabaseCount('product_relations', 2);

        $this->actingAs($staff)->put(route('panel.catalog.products.relations.update', $sourceId), ['relations' => []])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('product_relations', 0);
    }

    public function test_a_product_cannot_be_related_to_itself(): void
    {
        $staff = StaffAccount::factory()->create();
        $productId = $this->createProduct($staff, 'Produkt');

        $this->actingAs($staff)->put(route('panel.catalog.products.relations.update', $productId), [
            'relations' => [['type' => 'upsell', 'target_product_id' => $productId]],
        ])->assertSessionHasErrors('relations.0.target_product_id');

        $this->assertDatabaseCount('product_relations', 0);
    }

    public function test_the_same_relation_cannot_be_added_twice(): void
    {
        $staff = StaffAccount::factory()->create();
        $sourceId = $this->createProduct($staff, 'Produkt główny');
        $targetId = $this->createProduct($staff, 'Produkt docelowy');

        $this->actingAs($staff)->put(route('panel.catalog.products.relations.update', $sourceId), [
            'relations' => [
                ['type' => 'upsell', 'target_product_id' => $targetId],
                ['type' => 'upsell', 'target_product_id' => $targetId],
            ],
        ])->assertSessionHasErrors('relations.1.target_product_id');
    }

    public function test_relation_search_excludes_the_current_product_and_matches_by_name_or_sku(): void
    {
        $staff = StaffAccount::factory()->create();
        $sourceId = $this->createProduct($staff, 'Produkt źródłowy');
        $this->createProduct($staff, 'Zupełnie inna nazwa', 'FINDME-SKU');

        $response = $this->actingAs($staff)->getJson(route('panel.catalog.products.relations.search', $sourceId).'?q=FINDME');

        $response->assertOk();
        $skus = collect($response->json('data'))->pluck('sku')->all();
        $this->assertContains('FINDME-SKU', $skus);
        $this->assertNotContains($sourceId, collect($response->json('data'))->pluck('id')->all());
    }

    private function createProduct(StaffAccount $staff, string $name, ?string $sku = null): int
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl',
            'name' => $name,
            'short_description' => '',
            'description_html' => '',
            'type' => 'physical',
            'status' => 'draft',
            'featured' => false,
            'unit_code' => 'piece',
            'minimum_quantity' => '1',
            'maximum_quantity' => '',
            'quantity_step' => '1',
            'sku' => $sku ?? '',
            'gtin' => '',
            'mpn' => '',
            'brand_id' => '',
            'category_ids' => [],
            'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        return (int) DB::table('products')->max('id');
    }
}
