<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * /sitemap.xml (flat root index) + /{locale}/sitemap-{type}.xml — see
 * SitemapController's docblock for why the index can't nest per-language
 * sub-indexes (Google's "Nested indexing" rejection).
 */
final class SitemapTest extends TestCase
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

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'noindex' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'noindex' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->staff = StaffAccount::factory()->create();
    }

    public function test_root_index_lists_pl_entries_but_excludes_the_noindexed_en_language(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<loc>'.url('/pl/sitemap-products.xml').'</loc>', false);
        $response->assertSee('<loc>'.url('/pl/sitemap-pages.xml').'</loc>', false);
        $response->assertDontSee('/en/sitemap-', false);
    }

    public function test_products_sitemap_contains_published_products_but_not_drafts(): void
    {
        $published = $this->createDigitalProduct('Opublikowany produkt', '19.99', 'published');
        $draft = $this->createDigitalProduct('Szkic produktu', '19.99', 'draft');

        $response = $this->get('/pl/sitemap-products.xml')->assertOk();
        $response->assertSee('<loc>'.url('/pl/products/'.$published['slug']).'</loc>', false);
        $response->assertDontSee($draft['slug'], false);
    }

    public function test_products_sitemap_enumerates_variant_urls_only_for_indexed_separately_products(): void
    {
        $canonical = $this->createProductWithColorSizeVariants('Koszulka canonical', 'SHIRT-CANON', 'a');
        $separate = $this->createProductWithColorSizeVariants('Koszulka osobno', 'SHIRT-SEP', 'b');
        DB::table('products')->where('id', $separate['productId'])->update(['variant_seo_mode' => 'indexed_separately']);

        // Both products' axes are named "Kolor"/"Rozmiar" — the second attribute's
        // slug gets a disambiguating "-2" suffix (SlugGenerator, global per-locale
        // uniqueness, see CatalogAttributeManagementTest) — read the real slugs
        // rather than assume they're the bare words.
        $colorSlug = (string) DB::table('attribute_definition_translations')->where('locale', 'pl')->where('name', 'Kolor')->latest('id')->value('slug');
        $sizeSlug = (string) DB::table('attribute_definition_translations')->where('locale', 'pl')->where('name', 'Rozmiar')->latest('id')->value('slug');
        $redSlug = (string) DB::table('attribute_value_translations')->where('locale', 'pl')->where('name', 'Czerwony')->latest('id')->value('slug');
        $sSlug = (string) DB::table('attribute_value_translations')->where('locale', 'pl')->where('name', 'S')->latest('id')->value('slug');

        $response = $this->get('/pl/sitemap-products.xml')->assertOk();
        $response->assertSee('<loc>'.url('/pl/products/'.$canonical['slug']).'</loc>', false);
        $response->assertDontSee($canonical['slug'].'/kolor', false);
        $response->assertSee('<loc>'.url('/pl/products/'.$separate['slug']."/{$colorSlug}/{$redSlug}/{$sizeSlug}/{$sSlug}").'</loc>', false);
    }

    public function test_categories_and_brands_sitemaps_list_published_ones(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Odziez Testowa', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categorySlug = (string) DB::table('category_translations')->where('locale', 'pl')->value('slug');

        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.brands.store'), [
            'locale' => 'pl', 'name' => 'Marka Testowa', 'description_html' => '',
        ])->assertSessionHasNoErrors();
        $brandSlug = (string) DB::table('brand_translations')->where('locale', 'pl')->value('slug');
        $this->app['auth']->forgetGuards();

        $this->get('/pl/sitemap-categories.xml')->assertOk()->assertSee('<loc>'.url('/pl/'.$categorySlug).'</loc>', false);
        $this->get('/pl/sitemap-brands.xml')->assertOk()->assertSee('<loc>'.url('/pl/'.$brandSlug).'</loc>', false);
    }

    public function test_pages_sitemap_includes_the_homepage_at_the_locale_root_and_a_published_page_but_not_a_draft(): void
    {
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'szkic', 'title' => 'Szkic',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/pl/sitemap-pages.xml')->assertOk();
        $response->assertSee('<loc>'.url('/pl').'</loc>', false);
        $response->assertSee('<loc>'.url('/pl/o-nas').'</loc>', false);
        $response->assertDontSee('/pl/szkic', false);
    }

    public function test_creating_a_product_invalidates_the_cached_products_sitemap(): void
    {
        $this->get('/pl/sitemap-products.xml')->assertOk()->assertDontSee('<loc>', false);

        $product = $this->createDigitalProduct('Nowo dodany produkt', '19.99', 'published');

        $this->get('/pl/sitemap-products.xml')->assertOk()->assertSee('<loc>'.url('/pl/products/'.$product['slug']).'</loc>', false);
    }

    public function test_changing_a_language_invalidates_the_cached_root_index(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/en/sitemap-', false);

        $this->actingAs($this->staff)->put(route('panel.languages.update', 'en'), [
            'name' => 'English', 'is_enabled' => true, 'is_default' => false, 'noindex' => false,
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('/en/sitemap-products.xml').'</loc>', false);
    }

    public function test_robots_txt_points_to_the_sitemap_and_disallows_nothing(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Disallow:');
        $response->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    /** @return array{productId:int, variantId:int, slug:string} */
    private function createDigitalProduct(string $name, string $priceAmount, string $status): array
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => $status, 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        $this->actingAs($this->staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => $priceAmount, 'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'variantId' => $variantId, 'slug' => $slug];
    }

    /** @return array{productId:int, slug:string} */
    private function createProductWithColorSizeVariants(string $name, string $sku, string $attributeSuffix): array
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => $sku, 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'kolor'.$attributeSuffix, 'name' => 'Kolor', 'type' => 'color', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $colorId = (int) DB::table('attribute_definitions')->where('key', 'kolor'.$attributeSuffix)->value('id');
        $colorValues = [];
        foreach (['czerwony' => 'Czerwony', 'niebieski' => 'Niebieski'] as $key => $label) {
            $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-values.store', $colorId), [
                'locale' => 'pl', 'value_key' => $key, 'name' => $label, 'color_hex' => '#FF0000',
            ])->assertSessionHasNoErrors();
            $colorValues[] = (int) DB::table('attribute_values')->where('attribute_definition_id', $colorId)->where('value_key', $key)->value('id');
        }

        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'rozmiar'.$attributeSuffix, 'name' => 'Rozmiar', 'type' => 'single_select', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $sizeId = (int) DB::table('attribute_definitions')->where('key', 'rozmiar'.$attributeSuffix)->value('id');
        $sizeValues = [];
        foreach (['s' => 'S', 'm' => 'M'] as $key => $label) {
            $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-values.store', $sizeId), [
                'locale' => 'pl', 'value_key' => $key, 'name' => $label,
            ])->assertSessionHasNoErrors();
            $sizeValues[] = (int) DB::table('attribute_values')->where('attribute_definition_id', $sizeId)->where('value_key', $key)->value('id');
        }

        $this->actingAs($this->staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [
                ['attribute_definition_id' => $colorId, 'attribute_value_ids' => $colorValues],
                ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => $sizeValues],
            ],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'slug' => $slug];
    }
}
