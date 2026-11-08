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
 * <link rel="alternate" hreflang="..."> tags in theme::layout's <head> —
 * sourced from the SAME Storefront\Infrastructure\RouteAwareLanguageAlternateResolver
 * as the language switcher (see LanguageSwitcherTest), plus a self-reference
 * and x-default the switcher itself doesn't need. A variant deep-link page
 * deliberately points at the BASE product URL, not the variant path — see
 * docs/knowledge-base/09-presentation.md.
 */
final class HreflangTest extends TestCase
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
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/default-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();

        $this->staff = StaffAccount::factory()->create();
    }

    public function test_hreflang_tags_are_absent_when_the_store_has_only_one_language(): void
    {
        DB::table('languages')->where('code', 'en')->delete();

        $this->get('/pl')->assertOk()->assertDontSee('hreflang', false);
    }

    public function test_homepage_renders_self_and_other_locale_hreflang_tags(): void
    {
        $response = $this->get('/pl')->assertOk();

        $response->assertSee('<link rel="alternate" hreflang="pl" href="'.url('/pl').'">', false);
        $response->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en').'">', false);
        // pl is the default language and this IS the pl page, so x-default is the self reference.
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/pl').'">', false);
    }

    public function test_product_base_url_renders_hreflang_pointing_to_base_urls_in_each_locale(): void
    {
        $product = $this->createDigitalProduct('Ebook testowy', '19.99');
        DB::table('product_translations')->insert([
            'product_id' => $product['productId'], 'locale' => 'en', 'name' => 'Test ebook', 'slug' => 'test-ebook',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->get('/pl/products/'.$product['slug'])->assertOk();

        $response->assertSee('<link rel="alternate" hreflang="pl" href="'.url('/pl/products/'.$product['slug']).'">', false);
        $response->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/products/test-ebook').'">', false);
    }

    public function test_product_variant_deep_link_hreflang_points_to_base_product_url_not_the_variant_path(): void
    {
        $data = $this->createProductWithColorSizeVariants();
        DB::table('product_translations')->insert([
            'product_id' => $data['productId'], 'locale' => 'en', 'name' => 'T-Shirt', 'slug' => 't-shirt',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->get('/pl/products/'.$data['slug'].'/kolor/czerwony/rozmiar/s')->assertOk();

        // Self (pl) and the "en" alternate both drop the variant path — the base product URL only.
        $response->assertSee('<link rel="alternate" hreflang="pl" href="'.url('/pl/products/'.$data['slug']).'">', false);
        $response->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/products/t-shirt').'">', false);
        $response->assertDontSee('kolor/czerwony', false);
    }

    public function test_x_default_hreflang_points_to_the_default_languages_version_of_the_page(): void
    {
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'o-nas', 'title' => 'O nas',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        $this->actingAs($this->staff)->put(route('panel.cms.pages.update', $pageId).'?locale=en', [
            'root' => ['props' => ['title' => 'About us', 'slug' => 'about-us', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/en/about-us')->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/pl/o-nas').'">', false);
    }

    public function test_x_default_falls_back_to_default_locales_homepage_when_no_equivalent_page_exists(): void
    {
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'kontakt-en-only', 'title' => 'Placeholder',
            'seo_title' => '', 'seo_description' => '', 'status' => 'draft',
        ])->assertSessionHasNoErrors();
        $pageId = (int) DB::table('cms_pages')->latest('id')->value('id');
        // English-only published translation, deliberately with no pl row at all.
        DB::table('cms_page_translations')->where('page_id', $pageId)->where('locale', 'pl')->delete();
        $this->actingAs($this->staff)->put(route('panel.cms.pages.update', $pageId).'?locale=en', [
            'root' => ['props' => ['title' => 'Contact', 'slug' => 'contact', 'seo_title' => '', 'seo_description' => '', 'status' => 'published']],
            'content' => [],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $response = $this->get('/en/contact')->assertOk();
        $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/pl').'">', false);
    }

    /** @return array{productId:int, variantId:int, slug:string} */
    private function createDigitalProduct(string $name, string $priceAmount): array
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => 'Opis krotki', 'description_html' => '<p>Opis</p>',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
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

    /**
     * Same product/attribute/variant shape as
     * StorefrontPagesTest::test_product_page_variant_picker_resolves_exact_variant_with_price_and_promo,
     * without price/promo — this suite only cares about the URLs.
     *
     * @return array{productId:int, slug:string}
     */
    private function createProductWithColorSizeVariants(): array
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Koszulka', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => 'SHIRT-BASE', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'kolor', 'name' => 'Kolor', 'type' => 'color', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $colorId = (int) DB::table('attribute_definitions')->where('key', 'kolor')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-values.store', $colorId), [
            'locale' => 'pl', 'value_key' => 'czerwony', 'name' => 'Czerwony', 'color_hex' => '#FF0000',
        ])->assertSessionHasNoErrors();
        $colorValueId = (int) DB::table('attribute_values')->where('attribute_definition_id', $colorId)->where('value_key', 'czerwony')->value('id');

        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attributes.store'), [
            'locale' => 'pl', 'key' => 'rozmiar', 'name' => 'Rozmiar', 'type' => 'single_select', 'is_filterable' => true,
        ])->assertSessionHasNoErrors();
        $sizeId = (int) DB::table('attribute_definitions')->where('key', 'rozmiar')->value('id');
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.attribute-values.store', $sizeId), [
            'locale' => 'pl', 'value_key' => 's', 'name' => 'S',
        ])->assertSessionHasNoErrors();
        $sizeValueId = (int) DB::table('attribute_values')->where('attribute_definition_id', $sizeId)->where('value_key', 's')->value('id');

        $this->actingAs($this->staff)->post(route('panel.catalog.products.variants.generate', $productId), [
            'options' => [
                ['attribute_definition_id' => $colorId, 'attribute_value_ids' => [$colorValueId]],
                ['attribute_definition_id' => $sizeId, 'attribute_value_ids' => [$sizeValueId]],
            ],
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return ['productId' => $productId, 'slug' => $slug];
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
