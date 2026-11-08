<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class FailureScenariosTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
    }

    public function test_a_full_or_unwritable_private_disk_fails_the_upload_cleanly_and_leaves_no_half_created_file(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        config(['filesystems.disks.full' => ['driver' => 'local', 'root' => '/proc/litecommerce-full', 'throw' => false], 'media.private_disk' => 'full']);

        $response = $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik', 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
        ]);

        $this->assertLessThan(500, $response->getStatusCode(), 'a storage failure must not be a server error page');
        $this->assertSame(0, DB::table('private_files')->count());
        $this->assertSame(0, DB::table('variant_file_sets')->count());
    }
}
