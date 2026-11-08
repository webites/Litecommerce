<?php

declare(strict_types=1);

namespace Tests\Feature\Themes;

use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;
use ZipArchive;

/**
 * Coverage specific to resources/themes/react-storefront (engine:
 * blade-react) — that its two React islands actually get mounted with the
 * right data, on top of StorefrontPagesTest's coverage of the shared JSON
 * API those islands call. Not a re-test of the API itself.
 */
final class ReactStorefrontThemeTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);

        $zipPath = $this->zipDirectory(base_path('resources/themes/react-storefront'));
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
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

    /** @return array{slug:string} */
    private function createDigitalProduct(string $name, string $priceAmount): array
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '<p>Opis</p>',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $slug = (string) DB::table('product_translations')->where('product_id', $productId)->value('slug');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => $priceAmount, 'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();

        return ['slug' => $slug];
    }

    public function test_product_page_mounts_the_product_purchase_island_with_offers_and_currency(): void
    {
        $product = $this->createDigitalProduct('Ebook reactowy', '25.00');

        $response = $this->get('/pl/products/'.$product['slug'])->assertOk();
        $response->assertSee('id="product-purchase-root"', false);
        $response->assertSee('data-currency="PLN"', false);
        $response->assertSee('data-currency-decimals="2"', false);
        // No option axes on this product -> serialized as an empty list,
        // and a single offer (empty optionSignature) carrying the price.
        $response->assertSee("data-axes='[]'", false);
        $response->assertSee('25.00');
    }

    public function test_cart_and_checkout_pages_mount_the_cart_checkout_island_with_the_right_step(): void
    {
        $cart = $this->get('/pl/cart')->assertOk();
        $cart->assertSee('id="cart-checkout-root"', false);
        $cart->assertSee('data-step="cart"', false);

        $checkout = $this->get('/pl/checkout')->assertOk();
        $checkout->assertSee('id="cart-checkout-root"', false);
        $checkout->assertSee('data-step="checkout"', false);
    }

    public function test_account_deletion_pages_render_in_this_theme(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'buyer@example.com', 'password' => 'CorrectPassword123!']);

        $this->actingAs($customer, 'customer')->get('/pl/account/delete')->assertOk()->assertSee('/customer/account/deletion', false);
        $this->get('/pl/account/delete/'.str_repeat('a', 64))->assertOk()->assertSee('/customer/account/deletion/confirm', false);
    }

    public function test_consent_pages_and_the_checkout_dictionary_are_available_in_this_theme(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'buyer@example.com', 'password' => 'CorrectPassword123!']);

        $this->get('/pl/account/register')->assertOk()->assertSee('name="marketing_consent"', false);
        $this->actingAs($customer, 'customer')->get('/pl/account/preferences')->assertOk()->assertSee('/customer/account/marketing-consent', false);
        $this->get('/pl/marketing/unsubscribe/'.str_repeat('a', 40))->assertOk();
        $this->get('/pl/checkout')->assertOk()->assertSee('"marketing":{"statement"', false);
    }

    public function test_the_built_bundle_is_published_and_referenced_by_the_layout(): void
    {
        $response = $this->get('/pl')->assertOk();
        $response->assertSee('/storage/themes/react-storefront/js/app.js', false);
        $this->assertTrue(is_file(storage_path('app/public/themes/react-storefront/js/app.js')));
    }
}
