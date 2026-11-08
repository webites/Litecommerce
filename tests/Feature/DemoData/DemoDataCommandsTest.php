<?php

declare(strict_types=1);

namespace Tests\Feature\DemoData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Store\Application\SetBaseCurrency;
use Litecommerce\Modules\Store\Application\SetDefaultLanguage;
use Litecommerce\Modules\Warehouse\Contracts\WarehouseProvisioner;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class DemoDataCommandsTest extends TestCase
{
    use UsesIsolatedStorage;

    private string $previousMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->previousMemoryLimit = (string) ini_get('memory_limit');
        ini_set('memory_limit', '256M');
        $this->artisan('migrate', ['--force' => true]);
        app(SetDefaultLanguage::class)->handle('pl', 'Polski');
        app(SetBaseCurrency::class)->handle('PLN', 'Polski złoty', 'zł', 2);
        app(WarehouseProvisioner::class)->ensureDefaultWarehouseExists();
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->previousMemoryLimit);
        parent::tearDown();
    }

    public function test_the_full_demo_data_cycle_tracks_ids_removes_only_demo_records_and_can_be_repeated(): void
    {
        $userCategoryId = DB::table('categories')->insertGetId([
            'parent_id' => null,
            'position' => 0,
            'visible_in_tree' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('category_translations')->insert([
            'category_id' => $userCategoryId,
            'locale' => 'pl',
            'name' => 'Kategoria użytkownika',
            'slug' => 'kategoria-uzytkownika',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('demo-data:seed')->assertSuccessful();

        $batchId = (string) DB::table('demo_data_batches')->where('status', 'ready')->value('id');
        $this->assertNotSame('', $batchId);
        $this->assertDatabaseHas('demo_data_batches', ['id' => $batchId, 'active_key' => 'store', 'locale' => 'pl', 'currency_code' => 'PLN']);
        $this->assertSame(181, DB::table('demo_data_resources')->where('batch_id', $batchId)->count());
        $this->assertSame(12, DB::table('blog_posts')->count());
        $this->assertSame(12, DB::table('blog_posts')->whereNotNull('featured_media_id')->count());
        $this->assertSame(12, DB::table('blog_post_translations')->where('content_html', 'like', '%<ul>%')->where('content_html', 'like', '%<ol>%')->where('content_html', 'like', '%<img%')->count());
        $this->assertSame(6, DB::table('blog_categories')->count());
        $this->assertSame(18, DB::table('products')->count());
        $this->assertSame(30, DB::table('product_variants')->count());
        $multiVariantProducts = DB::table('products')
            ->join('product_variants', 'product_variants.product_id', '=', 'products.id')
            ->select('products.id')
            ->groupBy('products.id')
            ->havingRaw('COUNT(product_variants.id) > 1');
        $this->assertSame(6, DB::query()->fromSub($multiVariantProducts, 'multi_variant_products')->count());
        $productDescriptions = DB::table('product_translations')->pluck('description_html');
        $this->assertCount(18, $productDescriptions);
        foreach ($productDescriptions as $description) {
            $this->assertGreaterThanOrEqual(400, mb_strlen(strip_tags((string) $description)));
            $this->assertGreaterThanOrEqual(2, substr_count((string) $description, '<h2>'));
            $this->assertGreaterThanOrEqual(2, substr_count((string) $description, '<h3>'));
            $this->assertStringContainsString('<ul>', (string) $description);
        }
        $variantDescriptions = DB::table('variant_translations')->pluck('description_html');
        $this->assertCount(30, $variantDescriptions);
        $this->assertSame(30, DB::table('variant_translations')->distinct()->count('description_html'));
        foreach ($variantDescriptions as $description) {
            $this->assertGreaterThanOrEqual(180, mb_strlen(strip_tags((string) $description)));
            $this->assertSame(1, substr_count((string) $description, '<h2>'));
            $this->assertGreaterThanOrEqual(2, substr_count((string) $description, '<h3>'));
        }
        $this->assertSame(18, DB::table('media_attachments')->where('owner_type', 'product')->count());
        $this->assertSame(6, DB::table('media')->count());
        // 6 originals (1200x800 demo JPEGs) + 3 generated thumbnails each (320/640/960 — 1280 is skipped, never upscale
        // past the original's own 1200 width) = 24 physical files; see Media\Infrastructure\ImageThumbnailer.
        $this->assertCount(24, Storage::disk('public')->allFiles('catalog'));
        $this->assertSame(30, DB::table('variant_prices')->count());
        $this->assertSame(30, DB::table('stock_items')->count());
        $this->assertSame(9, DB::table('cms_pages')->count());
        $this->assertSame(1, DB::table('menus')->count());

        $firstProductId = (int) DB::table('demo_data_resources')
            ->where('batch_id', $batchId)->where('contributor', 'catalog')->where('resource_type', 'product')
            ->orderBy('position')->value('resource_id');
        DB::table('product_translations')->where('product_id', $firstProductId)->update(['name' => 'Nazwa zmieniona przez użytkownika']);

        $this->artisan('demo-data:seed')->assertFailed();
        $this->assertSame(18, DB::table('products')->count());
        $this->artisan('demo-data:list')->expectsOutputToContain($batchId)->assertSuccessful();

        $this->artisan('demo-data:remove', ['batch' => $batchId, '--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('demo_data_batches', ['id' => $batchId, 'status' => 'removed', 'active_key' => null]);
        $this->assertSame(181, DB::table('demo_data_resources')->where('batch_id', $batchId)->count());
        $this->assertDatabaseHas('categories', ['id' => $userCategoryId]);
        $this->assertDatabaseHas('category_translations', ['category_id' => $userCategoryId, 'name' => 'Kategoria użytkownika']);
        $this->assertSame(1, DB::table('categories')->count());
        $this->assertSame(0, DB::table('brands')->count());
        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('variant_prices')->count());
        $this->assertSame(0, DB::table('stock_items')->count());
        $this->assertSame(0, DB::table('blog_posts')->count());
        $this->assertSame(0, DB::table('blog_categories')->count());
        $this->assertSame(0, DB::table('cms_pages')->count());
        $this->assertSame(0, DB::table('menus')->count());
        $this->assertSame(0, DB::table('media')->count());
        $this->assertSame(0, DB::table('media_attachments')->count());
        $this->assertSame(0, DB::table('attribute_definitions')->count());
        $this->assertSame([], Storage::disk('public')->allFiles('catalog'));

        $this->artisan('demo-data:seed')->assertSuccessful();
        $this->assertSame(18, DB::table('products')->count());
        $this->assertSame(2, DB::table('demo_data_batches')->count());
    }

    public function test_removal_tolerates_a_demo_record_already_removed_manually(): void
    {
        $this->artisan('demo-data:seed')->assertSuccessful();
        $batchId = (string) DB::table('demo_data_batches')->where('status', 'ready')->value('id');
        $postId = (int) DB::table('demo_data_resources')
            ->where('batch_id', $batchId)->where('contributor', 'blog')->where('resource_type', 'post')
            ->value('resource_id');
        DB::table('blog_posts')->where('id', $postId)->delete();

        $this->artisan('demo-data:remove', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseHas('demo_data_batches', ['id' => $batchId, 'status' => 'removed']);
        $this->assertSame(0, DB::table('blog_posts')->count());
        $this->assertSame(0, DB::table('products')->count());
    }

    public function test_seeding_requires_store_language_and_currency_configuration(): void
    {
        DB::table('languages')->delete();
        DB::table('shop_currencies')->delete();

        $this->artisan('demo-data:seed')
            ->expectsOutputToContain('skonfiguruj aktywny język')
            ->assertFailed();

        $this->assertSame(0, DB::table('demo_data_batches')->count());
    }

    public function test_the_standard_laravel_seeder_uses_the_same_tracked_batch(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\DemoStoreSeeder'])
            ->assertSuccessful();

        $batchId = (string) DB::table('demo_data_batches')->where('status', 'ready')->value('id');
        $this->assertSame(181, DB::table('demo_data_resources')->where('batch_id', $batchId)->count());
        $this->artisan('demo-data:remove', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('products')->count());
    }

    public function test_media_file_is_removed_if_its_database_transaction_rolls_back(): void
    {
        try {
            DB::transaction(function (): void {
                app(MediaLibrary::class)->store('demo image', 'demo.jpg', 'image/jpeg');

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('catalog'));
    }

    /**
     * attribute_definition_translations/attribute_value_translations.slug
     * (added for the variant URL path, see CatalogAttributeManagementTest)
     * must be populated by CatalogDemoDataContributor, not just by the
     * panel's own store() endpoints — demo data creates these rows directly
     * via Eloquent, bypassing CatalogReferenceController entirely.
     */
    public function test_demo_data_seed_generates_slugs_for_attribute_definitions_and_values(): void
    {
        $this->artisan('demo-data:seed')->assertSuccessful();

        $this->assertSame(0, DB::table('attribute_definition_translations')->where('slug', '')->orWhereNull('slug')->count());
        $this->assertSame(0, DB::table('attribute_value_translations')->where('slug', '')->orWhereNull('slug')->count());
        $this->assertDatabaseHas('attribute_definition_translations', ['name' => 'Kolor', 'slug' => 'kolor']);
    }
}
