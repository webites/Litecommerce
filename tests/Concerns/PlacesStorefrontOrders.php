<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Themes\Contracts\ThemeInstaller;
use Litecommerce\Modules\Themes\Providers\ThemesServiceProvider;

trait PlacesStorefrontOrders
{
    use CarriesTestCookies;

    private int $pickupMethodId;

    private int $pickupPointId;

    protected function setUpShop(): void
    {
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Test', 'account_number' => 'PL001234', 'instructions' => json_encode(['pl' => 'x']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouses')->insert(['name' => 'Magazyn', 'created_at' => now(), 'updated_at' => now()]);
        $this->pickupMethodId = (int) DB::table('delivery_methods')->insertGetId(['kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->pickupPointId = (int) DB::table('delivery_pickup_points')->insertGetId([
            'delivery_method_id' => $this->pickupMethodId, 'name' => 'Sklep', 'country_code' => 'PL', 'postal_code' => '00-001',
            'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withCredentials();
    }

    protected function installReferenceTheme(): void
    {
        $sourceDir = base_path('resources/themes/default-storefront');
        $zipPath = tempnam(sys_get_temp_dir(), 'lc-theme-').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($sourceDir) + 1));
        }
        $zip->close();
        app(ThemeInstaller::class)->installAndActivate($zipPath);
        @unlink($zipPath);
        (new ThemesServiceProvider($this->app))->boot();
    }

    /** @return array{0: int, 1: int} product id, variant id */
    protected function createPhysicalVariant(StaffAccount $staff, string $price, string $mode = 'net', string $stock = '10', ?int $taxRateId = null): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Produkt '.uniqid(), 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
            'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        if ($taxRateId !== null) {
            $this->actingAs($staff)->put(route('panel.catalog.products.tax.update', $productId), ['tax_rate_id' => $taxRateId])->assertSessionHasNoErrors();
        }
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => $price, 'regular_mode' => $mode])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => $stock])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return [$productId, $variantId];
    }

    protected function startNewShopper(): void
    {
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->app['auth']->forgetGuards();
    }

    /** @return array{0: TestResponse, 1: string} confirm response, draft id */
    protected function checkoutVariant(int $variantId, string $quantity, string $paymentMethod = 'core.bank_transfer'): array
    {
        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => $quantity])->assertOk();
        $this->carryCookiesFrom($cart);
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->pickupMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');

        return [$this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => $paymentMethod]), $draftId];
    }

    protected function payOrder(string $orderId, StaffAccount $staff): void
    {
        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());
    }
}
