<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Identity\Infrastructure\Models\AccountPurchaseBlock;
use Litecommerce\Modules\Identity\Infrastructure\Models\CustomerAccount;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Checkout draft HTTP coverage: creation, live pricing with a
 * hash-confirmed commit, and the validation/idempotency/IDOR guarantees
 * from docs/knowledge-base/05-orders.md that don't need Delivery,
 * and the atomic hand-off to Orders/Payments.
 */
final class CheckoutTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    private int $deliveryMethodId;

    private int $pickupPointId;

    private array $billingAddress = [
        'recipient_name' => 'Jan Kowalski',
        'country_code' => 'PL',
        'postal_code' => '00-001',
        'city' => 'Warszawa',
        'address_line_1' => 'Testowa 1',
    ];

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
        DB::table('bank_transfer_accounts')->insert([
            'currency_code' => 'PLN', 'beneficiary_name' => 'Litecommerce Test', 'account_number' => 'PL001234',
            'instructions' => json_encode(['pl' => 'Tytułem numer zamówienia.']), 'enabled' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
        // A free pickup method — always available regardless of address —
        // so tests unrelated to Delivery can satisfy the now-mandatory
        // delivery selection for a physical cart with minimal setup.
        // Delivery-specific rules are covered in tests/Feature/Delivery/*.
        $this->deliveryMethodId = (int) DB::table('delivery_methods')->insertGetId([
            'kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pickupPointId = (int) DB::table('delivery_pickup_points')->insertGetId([
            'delivery_method_id' => $this->deliveryMethodId, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withCredentials();
    }

    private function createVariant(int $stock = 5): int
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();

        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');

        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), [
            'regular_amount' => '10.00',
            'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();

        if ($stock > 0) {
            $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => (string) $stock])
                ->assertSessionHasNoErrors();
        }

        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    /** Adds one item to a fresh guest cart and returns the created draft's id. */
    private function createDraftWithOneItem(int $variantId, int $quantity = 1): string
    {
        $first = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => (string) $quantity])->assertOk();
        $this->carryCookiesFrom($first);
        $draft = $this->postJson('/checkout')->assertCreated()->json();

        return $draft['id'];
    }

    public function test_creating_a_draft_from_an_empty_cart_is_rejected(): void
    {
        $this->postJson('/checkout')->assertStatus(422)->assertJsonPath('code', 'empty_cart');
    }

    public function test_repeated_checkout_creation_reuses_the_open_draft(): void
    {
        $variantId = $this->createVariant();
        $cartResponse = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cartResponse);

        $firstId = $this->postJson('/checkout')->assertCreated()->json('id');
        $secondId = $this->postJson('/checkout')->assertCreated()->json('id');

        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, DB::table('checkout_drafts')->count());
    }

    public function test_full_guest_checkout_flow_confirms_successfully(): void
    {
        $variantId = $this->createVariant();
        $draftId = $this->createDraftWithOneItem($variantId, 2);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person',
            'email' => 'guest@example.com',
            'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');
        $this->assertTrue($price['all_available']);
        $this->assertSame('20.00', $price['total_gross_amount']);

        $confirmed = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->json();
        $this->assertNotNull($confirmed['confirmed_at']);

        $this->assertDatabaseHas('carts', ['status' => 'checked_out']);

        // Replaying the same hash after confirmation is a no-op, not an error.
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');
    }

    /** @return array{0: string, 1: string} */
    private function confirmGuestCheckout(?bool $marketingConsent): array
    {
        $draftId = $this->createDraftWithOneItem($this->createVariant());
        $payload = [
            'buyer_type' => 'person', 'email' => 'Guest@Example.com', 'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false, 'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ];
        if ($marketingConsent !== null) {
            $payload['marketing_consent'] = $marketingConsent;
        }
        $this->putJson("/checkout/{$draftId}", $payload)->assertOk()->assertJsonPath('marketing_consent', (bool) $marketingConsent);
        $hash = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])->assertOk()->assertJsonPath('status', 'confirmed');

        return [$draftId, $hash];
    }

    public function test_a_ticked_marketing_consent_is_recorded_when_the_order_is_confirmed(): void
    {
        [$draftId, $hash] = $this->confirmGuestCheckout(true);
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])->assertOk();

        $this->assertDatabaseHas('marketing_consents', ['email' => 'guest@example.com', 'status' => 'granted', 'locale' => 'pl']);
        $this->assertDatabaseCount('marketing_consent_events', 1);
        $this->assertDatabaseHas('marketing_consent_events', ['action' => 'granted', 'source' => 'checkout']);
    }

    public function test_without_the_tick_no_consent_is_recorded(): void
    {
        $this->confirmGuestCheckout(null);

        $this->assertDatabaseCount('marketing_consents', 0);
    }

    public function test_a_tick_on_a_draft_that_is_never_confirmed_records_nothing(): void
    {
        $draftId = $this->createDraftWithOneItem($this->createVariant());
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com', 'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false, 'marketing_consent' => true,
        ])->assertOk();

        $this->assertDatabaseCount('marketing_consents', 0);
    }

    public function test_a_second_draft_cannot_confirm_after_the_cart_was_checked_out(): void
    {
        $variantId = $this->createVariant();
        $firstDraftId = $this->createDraftWithOneItem($variantId, 1);

        $this->putJson("/checkout/{$firstDraftId}", [
            'buyer_type' => 'person',
            'email' => 'guest@example.com',
            'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $firstDraft = CheckoutDraft::query()->findOrFail($firstDraftId);
        $secondDraft = $firstDraft->replicate();
        $secondDraft->id = (string) Str::uuid();
        $secondDraft->save();

        $hash = $this->getJson("/checkout/{$firstDraftId}")->assertOk()->json('price.hash');
        $this->postJson("/checkout/{$firstDraftId}/confirm", ['price_hash' => $hash])->assertOk();

        $this->postJson("/checkout/{$secondDraft->id}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)
            ->assertJsonPath('code', 'cart_checked_out');
        $this->assertDatabaseHas('checkout_drafts', ['id' => $secondDraft->id, 'status' => 'open']);
    }

    public function test_confirm_with_a_stale_hash_returns_the_fresh_price_instead_of_committing(): void
    {
        $variantId = $this->createVariant();
        $draftId = $this->createDraftWithOneItem($variantId, 1);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person',
            'email' => 'guest@example.com',
            'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $staleHash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        // The cart changes after the price was reviewed.
        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();

        $response = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $staleHash]);

        $response->assertStatus(409)->assertJsonPath('code', 'price_changed');
        $this->assertSame('20.00', $response->json('price.total_gross_amount'));
        $this->assertDatabaseHas('checkout_drafts', ['id' => $draftId, 'status' => 'open']);
    }

    /**
     * UpdateCheckoutDraftRequest requires every billing_address subfield
     * whenever billing_address is submitted at all, so a real client can
     * never persist a half-filled address — the only way to reach confirm
     * with billing_address still empty is to never have called update()
     * at all. An account customer covers that: email is auto-filled at
     * creation, so this isolates the address check specifically.
     */
    public function test_confirm_rejects_a_draft_with_no_billing_address_yet(): void
    {
        $variantId = $this->createVariant();
        $customer = CustomerAccount::query()->create(['email' => 'account@example.com', 'password' => 'irrelevant']);
        $this->actingAs($customer, 'customer');

        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $draftId = $this->postJson('/checkout')->assertCreated()->json('id');

        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)
            ->assertJsonPath('code', 'billing_address_required');
    }

    public function test_updating_a_draft_with_an_incomplete_billing_address_is_rejected(): void
    {
        $variantId = $this->createVariant();
        $draftId = $this->createDraftWithOneItem($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person',
            'email' => 'guest@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski'],
            'ships_to_different_address' => false,
        ])->assertStatus(422)->assertJsonValidationErrors(['billing_address.country_code']);
    }

    public function test_confirm_rejects_insufficient_stock_not_caught_at_add_time(): void
    {
        $variantId = $this->createVariant(stock: 2);
        $draftId = $this->createDraftWithOneItem($variantId, 5);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person',
            'email' => 'guest@example.com',
            'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)
            ->assertJsonPath('code', 'items_unavailable');
    }

    public function test_confirm_rejects_a_blocked_guest_email(): void
    {
        $customer = CustomerAccount::query()->create(['email' => 'blocked@example.com', 'password' => 'irrelevant']);
        AccountPurchaseBlock::query()->create(['customer_account_id' => $customer->getKey(), 'reason' => 'Fraud', 'blocked_at' => now()]);

        $variantId = $this->createVariant();
        $draftId = $this->createDraftWithOneItem($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person',
            'email' => 'blocked@example.com',
            'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)
            ->assertJsonPath('code', 'purchase_blocked');
    }

    public function test_an_account_customers_email_is_prefilled_and_cannot_be_overridden(): void
    {
        $variantId = $this->createVariant();

        // actingAs(..., 'customer') keeps this test focused on checkout's
        // own email-prefill behavior — registration's own HTTP endpoint is
        // covered separately in CartMergeTest.
        $customer = CustomerAccount::query()->create(['email' => 'account@example.com', 'password' => 'irrelevant']);
        $this->actingAs($customer, 'customer');

        $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();

        $draft = $this->postJson('/checkout')->assertCreated()->json();
        $this->assertSame('account@example.com', $draft['email']);

        // Attempting to spoof a different email is silently ignored, not an error.
        $updated = $this->putJson("/checkout/{$draft['id']}", [
            'buyer_type' => 'person',
            'email' => 'attacker@example.com',
            'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId,
            'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk()->json();
        $this->assertSame('account@example.com', $updated['email']);

        $hash = $this->getJson("/checkout/{$draft['id']}")->json('price.hash');
        $this->postJson("/checkout/{$draft['id']}/confirm", ['price_hash' => $hash])
            ->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('email', 'account@example.com');
    }

    public function test_a_draft_cannot_be_read_from_a_different_guest_session(): void
    {
        $variantId = $this->createVariant();
        $draftId = $this->createDraftWithOneItem($variantId);

        // A fresh "browser" — drop the cookies carryCookiesFrom() forwarded,
        // so this resolves to a brand new, different guest cart.
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->getJson("/checkout/{$draftId}")->assertNotFound();
    }
}
