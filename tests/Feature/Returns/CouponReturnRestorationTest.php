<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Coupons\Infrastructure\Models\Coupon;
use Litecommerce\Modules\Coupons\Infrastructure\Models\CouponLedgerEntry;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Litecommerce\Modules\Returns\Application\DecideReturnLines;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * The missing half of P1-010's "rzeczywiste przywrócenie salda kuponu" —
 * approving a return line whose order line carries a coupon allocation
 * must credit that same code's balance back
 * (docs/knowledge-base/06-fulfillment.md#projekt-techniczny-p1-010).
 */
final class CouponReturnRestorationTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    private int $deliveryMethodId;

    private int $pickupPointId;

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
        $this->deliveryMethodId = (int) DB::table('delivery_methods')->insertGetId(['kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->pickupPointId = (int) DB::table('delivery_pickup_points')->insertGetId([
            'delivery_method_id' => $this->deliveryMethodId, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withCredentials();
    }

    public function test_approving_a_full_return_restores_the_coupons_balance(): void
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
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '25.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '5'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $coupon = Coupon::query()->create([
            'code' => 'RETURN10', 'status' => 'active', 'balance' => '10.00', 'reserved_amount' => '0',
            'combinable_with_promotional' => true, 'combinable_with_other_coupons' => true, 'applies_to_delivery' => false,
        ]);
        CouponLedgerEntry::query()->create(['coupon_id' => $coupon->getKey(), 'type' => 'issue', 'amount' => '10.00', 'occurred_at' => now()]);

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $this->postJson('/cart/coupons', ['code' => 'RETURN10'])->assertOk();
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false, 'delivery_method_id' => 'core.'.$this->deliveryMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');
        $lineId = (int) DB::table('order_lines')->where('order_id', $orderId)->value('id');

        // Confirming full payment (15.00 = 25.00 - 10.00 coupon) also
        // confirms the coupon reservation — see CheckoutCouponTest for
        // that half; this test picks up from the already-consumed code.
        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());
        $coupon->refresh();
        $this->assertSame('0.000000', (string) $coupon->balance);

        $this->assertDatabaseHas('order_lines', ['id' => $lineId, 'line_gross_amount' => '15.000000']);

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');

        $refund = app(DecideReturnLines::class)->execute($request->id, [
            ['line_id' => $returnLineId, 'decision' => 'approved'],
        ], null, (string) $staff->getKey());

        $this->assertNotNull($refund);
        $this->assertSame('10.000000', $refund->couponRestoredAmount);
        $this->assertSame('5.000000', $refund->moneyRefundAmount);

        $coupon->refresh();
        $this->assertSame('10.000000', (string) $coupon->balance);
        $this->assertDatabaseHas('coupon_ledger_entries', [
            'coupon_id' => $coupon->getKey(), 'type' => 'restore', 'amount' => '10.000000',
            'reference_type' => 'return_line', 'reference_id' => (string) $returnLineId,
        ]);
    }
}
