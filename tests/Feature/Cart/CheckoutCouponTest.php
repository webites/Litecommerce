<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponLedgerEntry;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * End-to-end coverage of docs/knowledge-base/04-pricing-coupons.md's
 * "Kupony" section and the pipeline's steps 3/6 through the storefront
 * cart/checkout JSON API — attaching a code, its effect on live pricing,
 * and the reservation lifecycle once an order is actually placed.
 */
final class CheckoutCouponTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    private int $deliveryMethodId;

    private int $pickupPointId;

    private array $billingAddress = [
        'recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001',
        'city' => 'Warszawa', 'address_line_1' => 'Testowa 1',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Litecommerce Test', 'account_number' => 'PL001234', 'instructions' => json_encode(['pl' => 'Tytułem.']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
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

    private function createVariant(string $grossPrice = '25.00', int $stock = 5): int
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
            'regular_amount' => $grossPrice, 'regular_mode' => 'gross',
        ])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => (string) $stock])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    private function createCoupon(string $balance, array $overrides = []): Coupon
    {
        $coupon = Coupon::query()->create([
            'code' => $overrides['code'] ?? 'TEST'.random_int(10000, 99999), 'status' => $overrides['status'] ?? 'active',
            'balance' => $balance, 'reserved_amount' => '0',
            'combinable_with_promotional' => true, 'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
            ...array_diff_key($overrides, ['code' => null, 'status' => null]),
        ]);
        CouponLedgerEntry::query()->create(['coupon_id' => $coupon->getKey(), 'type' => 'issue', 'amount' => $balance, 'occurred_at' => now()]);

        return $coupon;
    }

    public function test_attaching_a_partial_coupon_discounts_the_cart_total(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        $response = $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertOk();
        $this->assertSame('15.00', $response->json('total_gross_amount'));
        $this->assertSame('applied', $response->json('coupons.0.status'));
        $this->assertSame('10.00', $response->json('coupons.0.allocated_amount'));

        // Storefront theme redesign (2026-09-18): "suma częściowa"/"rabat"/
        // "razem" as three separate, additive lines — subtotal reconstructs
        // the pre-discount figure from the now-net total_gross_amount.
        $this->assertSame('25.00', $response->json('subtotal_gross_amount'));
        $this->assertSame('10.00', $response->json('discount_amount'));
        $this->assertNull($response->json('lines.0.image_url'));
    }

    public function test_checkout_price_exposes_the_same_subtotal_and_discount_breakdown(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertOk();
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com', 'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false, 'delivery_method_id' => 'core.'.$this->deliveryMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        $response = $this->getJson("/checkout/{$draftId}")->assertOk();

        // This coupon does not apply to delivery (applies_to_delivery:
        // false in createCoupon()'s defaults), so the discount is entirely
        // the lines' share — subtotal (lines only, pre-discount) + discount
        // reconstructs price.total_gross_amount (lines only, post-discount).
        $this->assertSame('15.00', $response->json('price.total_gross_amount'));
        $this->assertSame('25.00', $response->json('price.subtotal_gross_amount'));
        $this->assertSame('10.00', $response->json('price.discount_amount'));
    }

    public function test_attaching_an_unknown_code_is_rejected(): void
    {
        $cart = $this->postJson('/cart/items', ['variant_id' => $this->createVariant(), 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        $this->postJson('/cart/coupons', ['code' => 'NOPE'])->assertStatus(422)->assertJsonPath('code', 'coupon_invalid_code');
    }

    public function test_pin_protected_coupon_requires_correct_pin(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');
        $coupon->update(['pin_hash' => bcrypt('4321')]);

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertStatus(422)->assertJsonPath('code', 'coupon_pin_required');
        $this->postJson('/cart/coupons', ['code' => $coupon->code, 'pin' => '0000'])->assertStatus(422)->assertJsonPath('code', 'coupon_pin_incorrect');
        $this->postJson('/cart/coupons', ['code' => $coupon->code, 'pin' => '4321'])->assertOk()->assertJsonPath('coupons.0.status', 'applied');
    }

    public function test_pin_locks_after_configured_attempts(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');
        $coupon->update(['pin_hash' => bcrypt('4321')]);

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        for ($i = 0; $i < (int) config('coupons.max_pin_attempts'); $i++) {
            $this->postJson('/cart/coupons', ['code' => $coupon->code, 'pin' => '0000'])->assertStatus(422);
        }

        $this->postJson('/cart/coupons', ['code' => $coupon->code, 'pin' => '4321'])
            ->assertStatus(422)->assertJsonPath('code', 'coupon_pin_locked');
    }

    public function test_blocked_and_expired_codes_are_rejected(): void
    {
        $variantId = $this->createVariant();
        $blocked = $this->createCoupon('10.00', ['status' => 'blocked']);
        $expired = $this->createCoupon('10.00', ['expires_at' => now()->subDay()]);

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        $this->postJson('/cart/coupons', ['code' => $blocked->code])->assertStatus(422)->assertJsonPath('code', 'coupon_blocked');
        $this->postJson('/cart/coupons', ['code' => $expired->code])->assertStatus(422)->assertJsonPath('code', 'coupon_expired');
    }

    public function test_detaching_a_coupon_removes_its_discount(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertOk();

        $response = $this->deleteJson("/cart/coupons/{$coupon->code}")->assertOk();
        $this->assertSame('25.00', $response->json('total_gross_amount'));
        $this->assertSame([], $response->json('coupons'));
    }

    public function test_two_codes_are_spent_in_a_waterfall_in_attachment_order(): void
    {
        $variantId = $this->createVariant('25.00');
        $first = $this->createCoupon('6.00', ['code' => 'FIRST']);
        $second = $this->createCoupon('100.00', ['code' => 'SECOND']);

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => $first->code])->assertOk();
        $response = $this->postJson('/cart/coupons', ['code' => $second->code])->assertOk();

        // FIRST takes 6.00 (its whole balance), SECOND takes the remaining 19.00 of the 25.00 line.
        $this->assertSame('0.00', $response->json('total_gross_amount'));
        $this->assertSame('6.00', $response->json('coupons.0.allocated_amount'));
        $this->assertSame('19.00', $response->json('coupons.1.allocated_amount'));
    }

    /**
     * "Pokrycie całości pomija operatora płatności i kieruje zamówienie do
     * realizacji" — the order is paid immediately, with no payment
     * attempt, and the coupon reservation is confirmed (permanently
     * consumed) atomically alongside the stock reservation.
     */
    public function test_full_coverage_places_a_paid_order_with_no_payment_attempt(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('100.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertOk();
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com', 'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false, 'delivery_method_id' => 'core.'.$this->deliveryMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');

        $response = $this->postJson("/checkout/{$draftId}/confirm", [
            'price_hash' => $hash, 'payment_method_id' => 'core.coupon_full_coverage',
        ])->assertOk();

        $orderId = (string) $response->json('order.id');
        $this->assertNull($response->json('payment'));
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'processing', 'payment_status' => 'paid']);
        $this->assertSame(0, DB::table('payment_attempts')->count());
        $this->assertDatabaseHas('order_coupon_usages', ['order_id' => $orderId, 'coupon_code' => $coupon->code, 'product_gross_amount' => '25.000000']);
        $this->assertDatabaseHas('coupon_reservations', ['reference_id' => $orderId, 'state' => 'confirmed']);
        $coupon->refresh();
        $this->assertSame('75.000000', (string) $coupon->balance);
    }

    public function test_partial_coverage_via_bank_transfer_confirms_the_coupon_reservation_on_manual_payment(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertOk();
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com', 'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false, 'delivery_method_id' => 'core.'.$this->deliveryMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');

        $this->assertDatabaseHas('coupon_reservations', ['reference_id' => $orderId, 'state' => 'pending']);
        $coupon->refresh();
        $this->assertSame('10.000000', (string) $coupon->reserved_amount);
        $this->assertSame('10.000000', (string) $coupon->balance);

        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, '1');

        $this->assertDatabaseHas('coupon_reservations', ['reference_id' => $orderId, 'state' => 'confirmed']);
        $coupon->refresh();
        $this->assertSame('0.000000', (string) $coupon->balance);
        $this->assertSame('0.000000', (string) $coupon->reserved_amount);
    }

    public function test_cancelling_an_unpaid_order_releases_the_coupon_reservation(): void
    {
        $variantId = $this->createVariant('25.00');
        $coupon = $this->createCoupon('10.00');

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => $coupon->code])->assertOk();
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com', 'billing_address' => $this->billingAddress,
            'ships_to_different_address' => false, 'delivery_method_id' => 'core.'.$this->deliveryMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');

        app(OrderPaymentManager::class)->cancelUnpaid($orderId, 'Rezygnacja klienta');

        $this->assertDatabaseHas('coupon_reservations', ['reference_id' => $orderId, 'state' => 'released']);
        $coupon->refresh();
        $this->assertSame('10.000000', (string) $coupon->balance);
        $this->assertSame('0.000000', (string) $coupon->reserved_amount);
    }
}
