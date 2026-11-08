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
 * The Delivery-specific slice of the checkout pipeline
 * (docs/knowledge-base/05-orders.md steps 4-5, docs/knowledge-base/06-fulfillment.md)
 * layered on top of the general checkout coverage in CheckoutTest: a
 * physical cart requires a delivery selection, the chosen quote is
 * snapshotted onto the placed order, and cash-on-delivery/pay-on-pickup
 * gate themselves on the selected delivery's declared capability.
 */
final class CheckoutDeliveryTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    private array $plAddress = [
        'recipient_name' => 'Jan Kowalski', 'country_code' => 'PL',
        'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Litecommerce Test', 'account_number' => 'PL001234', 'instructions' => json_encode(['pl' => 'x']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
        $this->withCredentials();
    }

    private function createPhysicalVariant(): int
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
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '5'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        return $variantId;
    }

    private function createDraft(int $variantId): string
    {
        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);

        return (string) $this->postJson('/checkout')->assertCreated()->json('id');
    }

    public function test_a_physical_checkout_cannot_confirm_without_choosing_delivery(): void
    {
        $variantId = $this->createPhysicalVariant();
        $draftId = $this->createDraft($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
        ])->assertOk();

        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)->assertJsonPath('code', 'delivery_method_required');
    }

    public function test_choosing_a_flat_rate_method_adds_its_price_to_the_total_and_snapshots_it_on_the_order(): void
    {
        $methodId = DB::table('delivery_methods')->insertGetId(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('delivery_region_rules')->insert(['delivery_method_id' => $methodId, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99', 'created_at' => now(), 'updated_at' => now()]);

        $variantId = $this->createPhysicalVariant();
        $draftId = $this->createDraft($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$methodId,
        ])->assertOk();

        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');
        $this->assertSame('10.00', $price['total_gross_amount']);
        $this->assertSame('14.99', $price['delivery']['selected']['gross_amount']);
        $this->assertSame('24.99', $price['grand_total_gross_amount']);

        $confirmed = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])->assertOk()->json();
        $orderId = $confirmed['order']['id'];

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'total_gross_amount' => '24.99']);
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'delivery_method_id' => 'core.'.$methodId, 'gross_amount' => '14.99']);
    }

    public function test_delivery_becoming_unavailable_after_selection_is_rejected_at_confirm(): void
    {
        $methodId = DB::table('delivery_methods')->insertGetId(['kind' => 'flat_rate', 'name' => 'Kurier krajowy', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('delivery_region_rules')->insert(['delivery_method_id' => $methodId, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99', 'created_at' => now(), 'updated_at' => now()]);

        $variantId = $this->createPhysicalVariant();
        $draftId = $this->createDraft($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$methodId,
        ])->assertOk();
        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        // Merchant excludes Poland from this method after the customer chose it.
        DB::table('delivery_region_rules')->insert(['delivery_method_id' => $methodId, 'rule_type' => 'exclude', 'country_code' => 'PL', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)->assertJsonPath('code', 'delivery_method_unavailable');
    }

    public function test_pickup_requires_choosing_a_pickup_point(): void
    {
        $methodId = DB::table('delivery_methods')->insertGetId(['kind' => 'pickup', 'name' => 'Odbiór', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('delivery_pickup_points')->insert(['delivery_method_id' => $methodId, 'name' => 'Sklep', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $variantId = $this->createPhysicalVariant();
        $draftId = $this->createDraft($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$methodId,
        ])->assertOk();
        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])
            ->assertStatus(422)->assertJsonPath('code', 'pickup_point_required');
    }

    public function test_cash_on_delivery_is_rejected_unless_the_selected_delivery_supports_it(): void
    {
        $withoutCod = DB::table('delivery_methods')->insertGetId(['kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('delivery_region_rules')->insert(['delivery_method_id' => $withoutCod, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '14.99', 'created_at' => now(), 'updated_at' => now()]);
        $withCod = DB::table('delivery_methods')->insertGetId(['kind' => 'flat_rate', 'name' => 'Kurier pobraniowy', 'active' => true, 'supports_cash_on_delivery' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('delivery_region_rules')->insert(['delivery_method_id' => $withCod, 'rule_type' => 'country_rate', 'country_code' => 'PL', 'price_amount' => '19.99', 'created_at' => now(), 'updated_at' => now()]);

        $variantId = $this->createPhysicalVariant();

        // Rejected when the chosen delivery does not declare COD support.
        $draftId = $this->createDraft($variantId);
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$withoutCod,
        ])->assertOk();
        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.cash_on_delivery'])
            ->assertStatus(422)->assertJsonPath('code', 'payment_method_unavailable');

        // Accepted once a COD-capable delivery is chosen instead.
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$withCod,
        ])->assertOk();
        $hash = $this->getJson("/checkout/{$draftId}")->json('price.hash');
        $response = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.cash_on_delivery'])->assertOk();

        $orderId = $response->json('order.id');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_method_id' => 'core.cash_on_delivery', 'payment_status' => 'unpaid']);
        // COD/pay-on-pickup never expire the stock hold waiting on an online provider.
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_deadline_at' => null]);
    }

    public function test_a_digital_only_cart_does_not_require_delivery(): void
    {
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'E-book testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $draftId = $this->createDraft($variantId);

        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'guest@example.com',
            'billing_address' => $this->plAddress, 'ships_to_different_address' => false,
        ])->assertOk();

        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');
        $this->assertNull($price['delivery']);

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash']])
            ->assertOk()->assertJsonPath('status', 'confirmed');
    }
}
