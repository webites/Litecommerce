<?php

declare(strict_types=1);

namespace Tests\Feature\Digital;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Panel-side file management for a digital variant — the "VariantFileSet"
 * / "DigitalAssetVersion" side of docs/knowledge-base/06-fulfillment.md#dostęp-cyfrowy.
 */
final class DigitalFileManagementTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function createDigitalVariant(StaffAccount $staff): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'draft', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');

        return [$productId, $variantId];
    }

    public function test_staff_can_upload_a_digital_file_and_a_new_version_without_losing_the_first(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createDigitalVariant($staff);

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik główny', 'max_downloads' => '3', 'validity_days' => '30',
            'file' => UploadedFile::fake()->createWithContent('ebook-v1.pdf', 'wersja pierwsza'),
        ])->assertSessionHasNoErrors();

        $fileId = (int) DB::table('variant_file_sets')->value('id');
        $this->assertDatabaseHas('variant_file_sets', [
            'id' => $fileId, 'variant_id' => $variantId, 'label' => 'Plik główny',
            'active' => true, 'max_downloads' => 3, 'validity_days' => 30,
        ]);
        $this->assertDatabaseHas('digital_asset_versions', ['variant_file_set_id' => $fileId, 'version_number' => 1, 'original_filename' => 'ebook-v1.pdf']);

        $this->actingAs($staff)->get(route('panel.catalog.products.edit', $productId))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('variants.0.digital_files.0.label', 'Plik główny')
                ->where('variants.0.digital_files.0.current_version.version_number', 1)
                ->where('variants.0.digital_files.0.current_version.original_filename', 'ebook-v1.pdf'));

        $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.versions.store', [$productId, $variantId, $fileId]), [
            'file' => UploadedFile::fake()->createWithContent('ebook-v2.pdf', 'wersja druga, poprawiona'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, DB::table('digital_asset_versions')->where('variant_file_set_id', $fileId)->count());
        $this->assertDatabaseHas('digital_asset_versions', ['variant_file_set_id' => $fileId, 'version_number' => 2, 'original_filename' => 'ebook-v2.pdf']);
        $this->assertDatabaseHas('variant_file_sets', ['id' => $fileId, 'current_version_id' => DB::table('digital_asset_versions')->where('version_number', 2)->value('id')]);
        // "Zmiana pliku nie zmienia wcześniejszych praw" — the v1 row and
        // its private_files backing are never removed, only superseded.
        $this->assertSame(2, DB::table('private_files')->count());
    }

    public function test_staff_can_update_settings_and_deactivate_a_file(): void
    {
        $staff = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createDigitalVariant($staff);
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik główny', 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
        ])->assertSessionHasNoErrors();
        $fileId = (int) DB::table('variant_file_sets')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.digital-files.update', [$productId, $variantId, $fileId]), [
            'label' => 'Plik główny (zaktualizowany)', 'max_downloads' => '5', 'validity_days' => '', 'active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('variant_file_sets', [
            'id' => $fileId, 'label' => 'Plik główny (zaktualizowany)', 'max_downloads' => 5, 'validity_days' => null, 'active' => false,
        ]);
    }

    public function test_a_role_without_catalog_permission_cannot_manage_digital_files(): void
    {
        $administrator = StaffAccount::factory()->create();
        [$productId, $variantId] = $this->createDigitalVariant($administrator);
        $warehouseWorker = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($warehouseWorker)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik', 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
        ])->assertForbidden();
    }
}
