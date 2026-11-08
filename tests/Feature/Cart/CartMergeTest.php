<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Customer registration/login and "Po logowaniu łączymy" guest-cart
 * merging from docs/knowledge-base/05-orders.md. Every step that must be
 * remembered by the next request — the guest cart cookie, the customer's
 * session — is carried forward explicitly via carryCookiesFrom(), since
 * Laravel's JSON test helpers do not do this automatically.
 */
final class CartMergeTest extends TestCase
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
        $this->withCredentials();
    }

    private function createVariant(string $type = 'physical'): int
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => $type, 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => $type === 'digital' ? '1' : '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '10.00',
            'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    public function test_registration_promotes_the_guest_cart_to_the_new_account(): void
    {
        $variantId = $this->createVariant();
        $added = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '2'])->assertOk();
        $this->carryCookiesFrom($added);

        $response = $this->postJson('/customer/register', [
            'email' => 'new@example.com',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ]);

        $response->assertCreated()->assertJsonPath('cart_merge.merged', true);

        $customerId = (int) DB::table('customer_accounts')->value('id');
        $this->assertDatabaseHas('carts', ['customer_account_id' => $customerId, 'guest_token' => null]);
        $this->assertDatabaseHas('cart_items', ['variant_id' => $variantId, 'quantity' => 2]);
    }

    /**
     * The account's pre-existing cart is seeded directly (not through a
     * second authenticated HTTP round trip) so this test exercises the
     * merge itself — the login endpoint's actual job — rather than
     * incidentally depending on the test session driver carrying an
     * authenticated session across two separate requests.
     */
    public function test_login_merges_the_guest_cart_into_an_existing_account_cart_and_clamps_digital_limit(): void
    {
        $digitalVariant = $this->createVariant('digital');

        $customerId = (int) CustomerAccount::query()->create([
            'email' => 'returning@example.com',
            'password' => Hash::make('correct horse battery staple'),
        ])->getKey();
        $accountCartId = DB::table('carts')->insertGetId([
            'customer_account_id' => $customerId, 'guest_token' => null, 'currency_code' => 'PLN',
            'status' => 'active', 'version' => 0, 'last_activity_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cart_items')->insert([
            'cart_id' => $accountCartId, 'variant_id' => $digitalVariant, 'quantity' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // A guest picks up the same digital item on a different device.
        $addedAsGuest = $this->postJson('/cart/items', ['variant_id' => $digitalVariant, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($addedAsGuest);

        $response = $this->postJson('/customer/login', [
            'email' => 'returning@example.com',
            'password' => 'correct horse battery staple',
        ]);

        $response->assertOk()
            ->assertJsonPath('cart_merge.merged', true)
            ->assertJsonPath('cart_merge.adjustments.0.variant_id', $digitalVariant)
            ->assertJsonPath('cart_merge.adjustments.0.adjusted_quantity', '1.000000');

        $this->assertDatabaseHas('cart_items', ['cart_id' => $accountCartId, 'variant_id' => $digitalVariant, 'quantity' => 1]);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        $registered = $this->postJson('/customer/register', [
            'email' => 'someone@example.com',
            'password' => 'correct horse battery staple',
            'password_confirmation' => 'correct horse battery staple',
        ])->assertCreated();
        $this->carryCookiesFrom($registered);
        $loggedOut = $this->postJson('/customer/logout')->assertNoContent();
        $this->carryCookiesFrom($loggedOut);

        $this->postJson('/customer/login', ['email' => 'someone@example.com', 'password' => 'wrong password'])
            ->assertStatus(422);
    }
}
