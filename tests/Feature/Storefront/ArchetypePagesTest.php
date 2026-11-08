<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * End-to-end coverage of the generic archetype mechanism (Cms\Contracts\
 * ArchetypeRegistry/ArchetypeResolver) — category and brand are the first
 * two registrants (Catalog\Providers\CatalogServiceProvider::boot()), routed
 * through the same flat /{locale}/{slug} catch-all as any plain Cms page.
 * See docs/knowledge-base/09-presentation.md's archetype pages section.
 */
final class ArchetypePagesTest extends TestCase
{
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        $this->rebootThemes();

        $this->staff = StaffAccount::factory()->create();
    }

    public function test_a_category_resolves_via_the_theme_default_archetype_template(): void
    {
        $slug = $this->createCategory('Buty sportowe');
        $this->createDigitalProduct('But do biegania', '99.00', $this->categoryIdBySlug($slug));

        $response = $this->get("/pl/{$slug}")->assertOk()
            ->assertSee('Buty sportowe')
            ->assertSee('But do biegania')
            ->assertSee('class="catalog-filter-block"', false)
            ->assertSee('action="http://localhost:8080/pl/'.$slug.'"', false)
            ->assertDontSee('name="category"', false);

        $this->get("/pl/{$slug}?min_price=100")->assertOk()
            ->assertDontSee('But do biegania')
            ->assertSee('value="100"', false);
    }

    public function test_a_brand_resolves_via_the_theme_default_archetype_template(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.brands.store'), [
            'locale' => 'pl', 'name' => 'Acme',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $brandSlug = (string) DB::table('brand_translations')->value('slug');

        $this->get("/pl/{$brandSlug}")->assertOk()->assertSee('Acme');
    }

    public function test_a_plain_heading_block_can_use_the_category_name_dynamic_tag(): void
    {
        $slug = $this->createCategory('Płaszcze');
        $this->saveArchetypeTemplate('category', 'Zakupy w kategorii {{category_name}}');

        $this->get("/pl/{$slug}")->assertOk()->assertSee('Zakupy w kategorii Płaszcze');
    }

    public function test_product_page_links_to_the_categorys_pretty_archive_url(): void
    {
        $slug = $this->createCategory('Torebki');
        $product = $this->createDigitalProduct('Torebka skórzana', '199.00', $this->categoryIdBySlug($slug));

        $response = $this->get('/pl/products/'.$product['slug']);

        $response->assertOk();
        $response->assertSee('/pl/'.$slug.'"', false);
        $response->assertDontSee('category=', false);
    }

    public function test_saving_the_editor_publishes_immediately_no_separate_status_toggle(): void
    {
        $slug = $this->createCategory('Kurtki');

        $this->saveArchetypeTemplate('category', 'Nagłówek nadpisany przez sprzedawcę');

        $this->assertDatabaseHas('cms_pages', ['archetype' => 'category', 'status' => 'published']);
        $this->get("/pl/{$slug}")->assertOk()->assertSee('Nagłówek nadpisany przez sprzedawcę');
    }

    public function test_merely_opening_the_editor_without_saving_does_not_create_a_live_override(): void
    {
        $slug = $this->createCategory('Spodnie');

        $this->actingAs($this->staff)->get(route('panel.cms.archetypes.edit', 'category'))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->get("/pl/{$slug}")->assertOk()->assertSee('Spodnie');
    }

    public function test_restoring_the_default_deletes_the_row_and_reverts_to_the_theme_template(): void
    {
        $slug = $this->createCategory('Czapki');
        $this->saveArchetypeTemplate('category', 'Nadpisany nagłówek');
        $this->get("/pl/{$slug}")->assertSee('Nadpisany nagłówek');

        $this->actingAs($this->staff)->post(route('panel.cms.archetypes.restore', 'category'))->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->assertDatabaseMissing('cms_pages', ['archetype' => 'category']);
        $this->get("/pl/{$slug}")->assertOk()->assertDontSee('Nadpisany nagłówek')->assertSee('Czapki');
    }

    public function test_restoring_one_archetype_never_touches_another_archetypes_override(): void
    {
        $categorySlug = $this->createCategory('Torby');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.brands.store'), [
            'locale' => 'pl', 'name' => 'Beta',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        $brandSlug = (string) DB::table('brand_translations')->where('name', 'Beta')->value('slug');

        $this->saveArchetypeTemplate('category', 'Nagłówek kategorii');
        $this->saveArchetypeTemplate('brand', 'Nagłówek marki');

        $this->actingAs($this->staff)->post(route('panel.cms.archetypes.restore', 'category'))->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->assertDatabaseMissing('cms_pages', ['archetype' => 'category']);
        $this->assertDatabaseHas('cms_pages', ['archetype' => 'brand', 'status' => 'published']);
        $this->get("/pl/{$categorySlug}")->assertOk()->assertDontSee('Nagłówek kategorii')->assertSee('Torby');
        $this->get("/pl/{$brandSlug}")->assertOk()->assertSee('Nagłówek marki');
    }

    public function test_a_plain_cms_page_wins_over_a_colliding_archetype_slug(): void
    {
        $slug = $this->createCategory('kolizja');
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => $slug, 'title' => 'Zwykła strona CMS',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get("/pl/{$slug}")->assertOk()->assertSee('Zwykła strona CMS')->assertDontSee('cms-archive-heading', false);
    }

    public function test_an_unmatched_slug_is_still_404(): void
    {
        $this->get('/pl/nic-takiego-nie-istnieje')->assertNotFound();
    }

    private function createCategory(string $name): string
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => $name, 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return (string) DB::table('category_translations')->where('name', $name)->value('slug');
    }

    private function categoryIdBySlug(string $slug): int
    {
        return (int) DB::table('category_translations')->where('slug', $slug)->value('category_id');
    }

    private function saveArchetypeTemplate(string $key, string $headingLabel): void
    {
        $this->actingAs($this->staff)->get(route('panel.cms.archetypes.edit', $key))->assertOk();
        $this->actingAs($this->staff)->put(route('panel.cms.archetypes.update', $key), [
            'root' => ['props' => ['title' => 'Szablon']],
            'content' => [
                ['id' => 'a', 'type' => 'heading', 'props' => ['text' => $headingLabel, 'level' => '2']],
            ],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
    }

    /** @return array{productId:int, variantId:int, slug:string} */
    private function createDigitalProduct(string $name, string $priceAmount, ?int $categoryId = null): array
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => 'Opis krotki', 'description_html' => '<p>Opis</p>',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '',
            'category_ids' => $categoryId === null ? [] : [$categoryId],
            'primary_category_id' => $categoryId === null ? '' : $categoryId,
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => $priceAmount, 'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'variantId' => $variantId, 'slug' => $slug];
    }

    private function rebootThemes(): void
    {
        (new ThemesServiceProvider($this->app))->boot();
    }

    private function zipDirectory(string $sourceDir): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'lc-theme-').'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zip->addFile($file->getPathname(), $relative);
        }
        $zip->close();

        return $zipPath;
    }
}
