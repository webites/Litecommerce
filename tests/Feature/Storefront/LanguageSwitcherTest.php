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
 * The storefront language switcher partial (theme::partials.language-switcher,
 * Storefront\Infrastructure\RouteAwareLanguageAlternateResolver) — user's own
 * spec: switching language keeps you on the equivalent page (e.g.
 * /pl/strona -> /en/page), falling back to that language's homepage when no
 * equivalent exists. See docs/knowledge-base/09-presentation.md.
 */
final class LanguageSwitcherTest extends TestCase
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

        // The menu is entirely merchant-configured (see StorefrontNavMenuTest) —
        // this test suite is about the switcher's OWN behavior, so give it a
        // 'primary' menu that includes it, the same way a real merchant would.
        DB::table('menus')->insert([
            'name' => 'Menu główne', 'location_id' => 'primary',
            'content' => json_encode([
                ['id' => 'lang', 'type' => 'widget', 'referenceKey' => 'language_switcher', 'referenceId' => null, 'url' => null, 'labelOverride' => null, 'targetBlank' => false, 'children' => []],
            ]),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->staff = StaffAccount::factory()->create();
    }

    public function test_the_switcher_is_absent_when_the_store_has_only_one_language(): void
    {
        DB::table('languages')->where('code', 'en')->delete();

        $this->get('/pl')->assertOk()->assertDontSee('sf-language-switcher', false);
    }

    public function test_homepage_switches_to_the_other_locales_homepage(): void
    {
        $this->get('/pl')->assertOk()->assertSee('href="/en" class="">English</a>', false);
    }

    public function test_a_cms_page_translated_in_both_locales_switches_to_its_own_equivalent_slug(): void
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

        $this->get('/pl/o-nas')->assertOk()->assertSee('href="/en/about-us" class="">English</a>', false);
    }

    public function test_a_cms_page_with_no_translation_falls_back_to_the_other_locales_homepage(): void
    {
        $this->actingAs($this->staff)->post(route('panel.cms.pages.store'), [
            'locale' => 'pl', 'slug' => 'kontakt', 'title' => 'Kontakt',
            'seo_title' => '', 'seo_description' => '', 'status' => 'published',
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get('/pl/kontakt')->assertOk()->assertSee('href="/en" class="">English</a>', false);
    }

    public function test_a_category_translated_in_both_locales_switches_to_its_own_equivalent_slug(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Buty', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $categoryId = (int) DB::table('categories')->value('id');
        DB::table('category_translations')->insert([
            'category_id' => $categoryId, 'locale' => 'en', 'name' => 'Shoes', 'slug' => 'shoes',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->app['auth']->forgetGuards();

        $this->get('/pl/buty')->assertOk()->assertSee('href="/en/shoes" class="">English</a>', false);
    }

    public function test_a_category_with_no_translation_falls_back_to_the_other_locales_homepage(): void
    {
        $this->actingAs($this->staff)->post(route('panel.catalog.products.references.categories.store'), [
            'locale' => 'pl', 'name' => 'Spodnie', 'parent_id' => null, 'visible_in_tree' => true,
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $this->get('/pl/spodnie')->assertOk()->assertSee('href="/en" class="">English</a>', false);
    }

    public function test_a_product_translated_in_both_locales_switches_to_its_own_equivalent_slug(): void
    {
        $product = $this->createDigitalProduct('Ebook testowy', '19.99');
        DB::table('product_translations')->insert([
            'product_id' => $product['productId'], 'locale' => 'en', 'name' => 'Test ebook', 'slug' => 'test-ebook',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get('/pl/products/'.$product['slug'])->assertOk()->assertSee('href="/en/products/test-ebook" class="">English</a>', false);
    }

    public function test_the_plain_product_listing_and_functional_pages_swap_the_locale_prefix(): void
    {
        $this->get('/pl/products')->assertOk()->assertSee('href="/en/products" class="">English</a>', false);
        $this->get('/pl/cart')->assertOk()->assertSee('href="/en/cart" class="">English</a>', false);
    }

    public function test_the_current_language_is_marked_active(): void
    {
        $this->get('/pl')->assertOk()->assertSee('href="/pl" class="is-active">Polski</a>', false);
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
