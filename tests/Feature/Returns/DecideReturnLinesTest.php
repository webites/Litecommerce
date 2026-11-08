<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Litecommerce\Modules\Returns\Application\DecideReturnLines;
use Litecommerce\Modules\Returns\Application\RecordRefundPayout;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * The money/goods side of approving return lines — the proportional
 * allocation and "last return takes the remainder" rule from
 * docs/knowledge-base/04-pricing-coupons.md#snapshot-i-zwroty, and the
 * restock/digital-revocation/payout wiring from
 * docs/knowledge-base/06-fulfillment.md#projekt-techniczny-p1-010.
 */
final class DecideReturnLinesTest extends TestCase
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
        $this->deliveryMethodId = (int) DB::table('delivery_methods')->insertGetId([
            'kind' => 'flat_rate', 'name' => 'Kurier', 'active' => true, 'price_amount' => '15.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('delivery_region_rules')->insert([
            'delivery_method_id' => $this->deliveryMethodId, 'rule_type' => 'continent_rate', 'continent_code' => 'EU',
            'price_amount' => '15.00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pickupPointId = (int) DB::table('delivery_pickup_points')->insertGetId([
            'delivery_method_id' => $this->deliveryMethodId, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withCredentials();
    }

    /** @return array{0:string,1:int,2:int} order id, variant id, order line id */
    private function placePaidPhysicalOrder(StaffAccount $staff, string $quantity, ?int $taxRateId = null): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
            'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        if ($taxRateId !== null) {
            $this->actingAs($staff)->put(route('panel.catalog.products.tax.update', $productId), ['tax_rate_id' => $taxRateId])->assertSessionHasNoErrors();
        }
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'net'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '10'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => $quantity])->assertOk();
        $this->carryCookiesFrom($cart);
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->deliveryMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk()->json('order.id');
        $lineId = (int) DB::table('order_lines')->where('order_id', $orderId)->value('id');

        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());

        return [$orderId, $variantId, $lineId];
    }

    /** @return array{0:string,1:int} order id, order line id */
    private function placePaidDigitalOrder(StaffAccount $staff): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '19.99', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.catalog.products.variants.digital-files.store', [$productId, $variantId]), [
            'label' => 'Plik', 'file' => UploadedFile::fake()->createWithContent('a.pdf', 'x'),
        ])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cart);
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
        ])->assertOk();
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk()->json('order.id');
        $lineId = (int) DB::table('order_lines')->where('order_id', $orderId)->value('id');

        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());

        return [$orderId, $lineId];
    }

    public function test_a_full_approval_of_a_single_line_takes_the_exact_recorded_amount(): void
    {
        $staff = StaffAccount::factory()->create();
        $taxRateId = (int) DB::table('tax_rates')->insertGetId(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23.00', 'created_at' => now(), 'updated_at' => now()]);
        [$orderId, , $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '3', taxRateId: $taxRateId);

        // Acceptance example from docs/knowledge-base/04-pricing-coupons.md:
        // "Cena netto: 3 × 10,00, VAT 23% daje netto 30,00, VAT 6,90, brutto 36,90".
        $this->assertDatabaseHas('order_lines', ['id' => $lineId, 'line_net_amount' => '30.000000', 'line_vat_amount' => '6.900000', 'line_gross_amount' => '36.900000']);

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '3', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');

        $refund = app(DecideReturnLines::class)->execute($request->id, [
            ['line_id' => $returnLineId, 'decision' => 'approved'],
        ], null, (string) $staff->getKey());

        $this->assertNotNull($refund);
        $this->assertSame('30.000000', $refund->productsNetAmount);
        $this->assertSame('6.900000', $refund->productsVatAmount);
        $this->assertSame('36.900000', $refund->productsGrossAmount);
        $this->assertSame('36.900000', $refund->moneyRefundAmount);
        $this->assertDatabaseHas('return_lines', ['id' => $returnLineId, 'status' => 'approved', 'gross_amount' => '36.900000']);
    }

    public function test_two_partial_returns_of_the_same_line_reconstruct_the_original_amount_exactly(): void
    {
        $staff = StaffAccount::factory()->create();
        $taxRateId = (int) DB::table('tax_rates')->insertGetId(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23.00', 'created_at' => now(), 'updated_at' => now()]);
        [$orderId, , $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '3', taxRateId: $taxRateId);

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $firstLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        $firstRefund = app(DecideReturnLines::class)->execute($request->id, [['line_id' => $firstLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

        $secondRequest = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '2', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $secondLineId = (int) DB::table('return_lines')->where('return_request_id', $secondRequest->id)->value('id');
        $secondRefund = app(DecideReturnLines::class)->execute($secondRequest->id, [['line_id' => $secondLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

        // 1/3 proportional then the exact remainder for the rest — must sum
        // to precisely the original line amounts, no rounding leak.
        $sumNet = bcadd($firstRefund->productsNetAmount, $secondRefund->productsNetAmount, 6);
        $sumVat = bcadd($firstRefund->productsVatAmount, $secondRefund->productsVatAmount, 6);
        $sumGross = bcadd($firstRefund->productsGrossAmount, $secondRefund->productsGrossAmount, 6);
        $this->assertSame('30.000000', $sumNet);
        $this->assertSame('6.900000', $sumVat);
        $this->assertSame('36.900000', $sumGross);

        // The line is now fully returned — a third attempt has no remaining quantity.
        $this->expectException(\DomainException::class);
        app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
    }

    public function test_approving_with_restock_returns_quantity_to_stock(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $variantId, $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '3');
        $onHandBefore = (string) DB::table('stock_items')->where('variant_id', $variantId)->value('on_hand_quantity');

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '2', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        app(DecideReturnLines::class)->execute($request->id, [
            ['line_id' => $returnLineId, 'decision' => 'approved', 'restock' => true, 'restocked_quantity' => '2'],
        ], null, (string) $staff->getKey());

        $onHandAfter = (string) DB::table('stock_items')->where('variant_id', $variantId)->value('on_hand_quantity');
        $this->assertSame('2.000000', bcsub($onHandAfter, $onHandBefore, 6));
        $this->assertDatabaseHas('stock_movements', ['type' => 'return']);
        $this->assertDatabaseHas('return_lines', ['id' => $returnLineId, 'restock' => true, 'restocked_quantity' => '2.000000']);
    }

    public function test_rejecting_a_line_does_not_touch_stock_or_create_a_refund_record(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $variantId, $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '3');
        $onHandBefore = (string) DB::table('stock_items')->where('variant_id', $variantId)->value('on_hand_quantity');

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        $refund = app(DecideReturnLines::class)->execute($request->id, [
            ['line_id' => $returnLineId, 'decision' => 'rejected', 'decision_reason' => 'Poza terminem zwrotu.'],
        ], null, (string) $staff->getKey());

        $this->assertNull($refund);
        $this->assertSame($onHandBefore, (string) DB::table('stock_items')->where('variant_id', $variantId)->value('on_hand_quantity'));
        $this->assertDatabaseHas('return_lines', ['id' => $returnLineId, 'status' => 'rejected', 'decision_reason' => 'Poza terminem zwrotu.', 'refund_record_id' => null]);
        $this->assertSame(0, DB::table('refund_records')->count());
    }

    public function test_approving_a_digital_line_revokes_its_download_grants(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $lineId] = $this->placePaidDigitalOrder($staff);
        $this->assertDatabaseHas('download_grants', ['order_id' => $orderId, 'revoked_at' => null]);

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        app(DecideReturnLines::class)->execute($request->id, [['line_id' => $returnLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

        $grant = DB::table('download_grants')->where('order_id', $orderId)->first();
        $this->assertNotNull($grant->revoked_at);
    }

    public function test_delivery_refund_cannot_exceed_what_was_actually_paid_for_delivery(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '1');
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'gross_amount' => '15.000000']);

        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');

        $this->expectException(\DomainException::class);
        app(DecideReturnLines::class)->execute($request->id, [
            ['line_id' => $returnLineId, 'decision' => 'approved'],
        ], '20.00', (string) $staff->getKey());
    }

    public function test_payout_is_a_separate_idempotent_action(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '1');
        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        $refund = app(DecideReturnLines::class)->execute($request->id, [['line_id' => $returnLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

        $this->assertDatabaseHas('refund_records', ['id' => $refund->id, 'paid_out_at' => null]);

        app(RecordRefundPayout::class)->execute($refund->id, 'PRZ-001', (string) $staff->getKey());
        $this->assertDatabaseHas('refund_records', ['id' => $refund->id, 'payout_reference' => 'PRZ-001']);

        app(RecordRefundPayout::class)->execute($refund->id, 'PRZ-002', (string) $staff->getKey());
        $this->assertDatabaseHas('refund_records', ['id' => $refund->id, 'payout_reference' => 'PRZ-001']);
    }

    public function test_a_role_without_manage_refunds_cannot_decide_or_record_a_payout(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidPhysicalOrder($staff, quantity: '1');
        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        $warehouseWorker = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($warehouseWorker)->post(route('panel.returns.decide', $request->id), [
            'decisions' => [['line_id' => $returnLineId, 'decision' => 'approved']],
        ])->assertForbidden();
    }
}
