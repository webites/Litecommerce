<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Guest cart HTTP coverage — a future storefront theme (P1-013) calls
 * this JSON API rather than server-rendered pages, so these tests hit
 * the routes directly, same as an admin-panel HTTP feature test.
 */
final class CartTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert([
            'code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('shop_currencies')->insert([
            'code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
        $this->withCredentials();
    }

    private function createVariant(string $type = 'physical', string $maxQuantity = '', bool $withPrice = true): int
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => $type, 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => $type === 'digital' ? '1' : $maxQuantity, 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');

        if ($withPrice) {
            $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
                'regular_amount' => '10.00',
                'regular_mode' => 'gross',
            ])->assertSessionHasNoErrors();
        }

        if ($type === 'physical') {
            $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '5'])
                ->assertSessionHasNoErrors();
        }

        // actingAs() sticks to the 'web' guard past this point — irrelevant
        // to the 'customer' guard Cart checks, but forget it for clarity.
        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    public function test_guest_can_add_an_item_and_see_it_priced(): void
    {
        $variantId = $this->createVariant();

        $response = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '2']);

        $response->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private')
            ->assertJsonPath('lines.0.variant_id', $variantId)
            ->assertJsonPath('lines.0.quantity', '2')
            ->assertJsonPath('lines.0.available', true)
            ->assertJsonPath('all_available', true);
    }

    public function test_adding_the_same_variant_twice_sums_the_quantity(): void
    {
        $variantId = $this->createVariant();

        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '2'])->assertOk();
        $this->carryCookiesFrom($first);
        $response = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '3'])->assertOk();

        $response->assertJsonCount(1, 'lines')->assertJsonPath('lines.0.quantity', '5');
    }

    public function test_digital_variant_cannot_be_added_twice(): void
    {
        $variantId = $this->createVariant('digital');

        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($first);
        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'quantity_out_of_bounds');
    }

    public function test_add_item_rejects_a_quantity_above_the_variant_maximum(): void
    {
        $variantId = $this->createVariant('physical', '3');

        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '4'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'quantity_out_of_bounds');
    }

    public function test_adding_a_variant_without_a_price_is_rejected(): void
    {
        $variantId = $this->createVariant(withPrice: false);

        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'no_price');
    }

    public function test_a_variant_that_loses_its_price_after_being_added_cannot_have_its_quantity_increased(): void
    {
        $variantId = $this->createVariant();
        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($first);

        DB::table('variant_prices')->where('variant_id', $variantId)->delete();

        $this->putJson("/cart/items/{$variantId}", ['quantity' => '2'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'no_price');
    }

    public function test_update_item_quantity_to_zero_removes_the_line(): void
    {
        $variantId = $this->createVariant();
        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '2'])->assertOk();
        $this->carryCookiesFrom($first);

        $response = $this->putJson("/cart/items/{$variantId}", ['quantity' => '0'])->assertOk();

        $response->assertJsonCount(0, 'lines');
    }

    public function test_removing_an_item(): void
    {
        $variantId = $this->createVariant();
        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($first);

        $response = $this->deleteJson("/cart/items/{$variantId}")->assertOk();

        $response->assertJsonCount(0, 'lines');
    }

    public function test_setting_an_unknown_currency_is_rejected(): void
    {
        $this->putJson('/cart/currency', ['currency_code' => 'ZZZ'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'unknown_currency');
    }

    public function test_setting_a_known_currency(): void
    {
        DB::table('shop_currencies')->insert([
            'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->putJson('/cart/currency', ['currency_code' => 'eur'])
            ->assertOk()
            ->assertJsonPath('currency_code', 'EUR');
    }

    public function test_a_disabled_variant_stays_visible_as_unavailable(): void
    {
        $variantId = $this->createVariant();
        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($first);

        DB::table('product_variants')->where('id', $variantId)->update(['enabled' => false]);

        $this->getJson('/cart')
            ->assertOk()
            ->assertJsonPath('lines.0.available', false)
            ->assertJsonPath('lines.0.unavailable_reason', 'unavailable')
            ->assertJsonPath('all_available', false);
    }

    public function test_a_fresh_request_without_the_cookie_gets_an_empty_cart(): void
    {
        $variantId = $this->createVariant();
        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();

        // No carryCookiesFrom() call — this is a different "browser".
        $this->getJson('/cart')->assertOk()->assertJsonCount(0, 'lines');
    }
}
