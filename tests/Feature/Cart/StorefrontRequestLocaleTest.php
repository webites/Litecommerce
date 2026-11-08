<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class StorefrontRequestLocaleTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    private int $variantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
        DB::table('languages')->insert(['code' => 'en', 'name' => 'English', 'is_default' => false, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        [$productId, $this->variantId] = $this->createPhysicalVariant(StaffAccount::factory()->create(), '10.00');
        DB::table('product_translations')->insert([
            'product_id' => $productId, 'locale' => 'en', 'name' => 'English product', 'slug' => 'english-product',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_cart_line_names_follow_the_page_language(): void
    {
        $headers = ['X-Storefront-Locale' => 'en'];

        $response = $this->postJson('/cart/items', ['variant_id' => $this->variantId, 'quantity' => '1'], $headers)->assertOk();
        $this->carryCookiesFrom($response);

        $this->getJson('/cart', $headers)->assertOk()->assertJsonPath('lines.0.display_name', 'English product');
        $this->assertStringStartsWith('Produkt', (string) $this->getJson('/cart')->json('lines.0.display_name'));
    }

    public function test_an_order_remembers_the_language_it_was_placed_in(): void
    {
        $this->withHeaders(['X-Storefront-Locale' => 'en']);

        [$confirm] = $this->checkoutVariant($this->variantId, '1');
        $confirm->assertOk();

        $this->assertSame('en', DB::table('orders')->where('id', $confirm->json('order.id'))->value('locale'));
    }
}
