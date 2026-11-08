<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Litecommerce\Modules\Returns\Application\DecideReturnLines;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class ReturnsPanelTest extends TestCase
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

    /** @return array{0:string,1:int} order id, order line id */
    private function placePaidOrder(StaffAccount $staff): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
            'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '25.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '5'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();

        $cart = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '2'])->assertOk();
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

        return [$orderId, $lineId];
    }

    public function test_an_unknown_return_request_redirects_to_the_listing_with_an_error(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.returns.show', '00000000-0000-0000-0000-000000000000'))
            ->assertRedirect(route('panel.returns.index'))
            ->assertSessionHas('error');
    }

    public function test_order_detail_page_lists_return_requests(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $lineId] = $this->placePaidOrder($staff);
        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);

        $this->actingAs($staff)->get(route('panel.orders.show', $orderId))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Orders/Show')
                ->where('order.returns.0.id', $request->id)
                ->where('order.returns.0.pending_count', 1));
    }

    public function test_return_detail_page_shows_lines_and_refund_after_decision(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $lineId] = $this->placePaidOrder($staff);
        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        app(DecideReturnLines::class)->execute($request->id, [['line_id' => $returnLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

        $this->actingAs($staff)->get(route('panel.returns.show', $request->id))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Returns/Show')
                ->where('returnRequest.lines.0.status', 'approved')
                ->has('returnRequest.refunds', 1));
    }

    public function test_marking_a_refund_as_paid_out_through_the_panel(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $lineId] = $this->placePaidOrder($staff);
        $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
        $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
        $refund = app(DecideReturnLines::class)->execute($request->id, [['line_id' => $returnLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

        $this->actingAs($staff)->post(route('panel.returns.refunds.payout', [$request->id, $refund->id]), ['reference' => 'PRZ-100'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('refund_records', ['id' => $refund->id, 'payout_reference' => 'PRZ-100']);
        $this->assertDatabaseHas('audit_entries', ['action' => 'returns.refund.paid_out']);
    }

    /**
     * Real bug fixed alongside P1-017: CreateReturnRequest/DecideReturnLines
     * used to throw bare \InvalidArgumentException/\DomainException with no
     * try/catch anywhere in ReturnController — a business-rule violation
     * (like this one) surfaced as a raw 500 instead of a validation error.
     */
    public function test_requesting_a_quantity_exceeding_what_is_left_to_return_is_a_friendly_validation_error_not_a_500(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, $lineId] = $this->placePaidOrder($staff);

        $response = $this->actingAs($staff)->post(route('panel.orders.returns.store', $orderId), [
            'comment' => null,
            'lines' => [['order_line_id' => $lineId, 'quantity' => '999', 'reason_id' => null, 'reason_comment' => null]],
        ]);

        $response->assertSessionHasErrors('lines');
        $this->assertStringContainsString((string) $lineId, (string) session('errors')->first('lines'));
        $this->assertDatabaseCount('return_requests', 0);
    }
}
