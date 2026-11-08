<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * "Częściowe anulowanie niezapłaconej pozycji nie może tworzyć fikcyjnego
 * zwrotu pieniędzy" — resolved in docs/knowledge-base/06-fulfillment.md#projekt-techniczny-p1-010
 * as: a return request only exists for an order that reached a paid
 * state.
 */
final class ReturnRequestCreationTest extends TestCase
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

    /** @return array{0:string,1:int,2:int} order id, variant id, order line id */
    private function placePaidOrder(StaffAccount $staff, string $quantity = '3'): array
    {
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Kubek testowy', 'short_description' => '', 'description_html' => '',
            'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
            'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
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

    public function test_staff_can_create_a_return_request_for_a_paid_order(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidOrder($staff);

        $this->actingAs($staff)->post(route('panel.orders.returns.store', $orderId), [
            'comment' => 'Klient zgłosił reklamację telefonicznie.',
            'lines' => [['order_line_id' => $lineId, 'quantity' => '2', 'reason_id' => null, 'reason_comment' => 'Uszkodzone opakowanie']],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('return_requests', ['order_id' => $orderId, 'source' => 'staff', 'status' => 'open']);
        $this->assertDatabaseHas('return_lines', ['order_line_id' => $lineId, 'quantity' => '2.000000', 'status' => 'pending']);
    }

    public function test_creating_a_return_for_an_unpaid_order_is_rejected(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidOrder($staff);
        DB::table('orders')->where('id', $orderId)->update(['payment_status' => 'unpaid', 'paid_at' => null]);

        $this->expectException(\DomainException::class);
        app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null],
        ]);
    }

    public function test_requested_quantity_cannot_exceed_the_ordered_quantity(): void
    {
        $staff = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidOrder($staff, quantity: '3');

        $this->expectException(\DomainException::class);
        app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
            ['order_line_id' => $lineId, 'quantity' => '4', 'reason_id' => null, 'reason_comment' => null],
        ]);
    }

    public function test_a_role_without_manage_refunds_cannot_create_a_return_request(): void
    {
        $administrator = StaffAccount::factory()->create();
        [$orderId, , $lineId] = $this->placePaidOrder($administrator);
        $warehouseWorker = StaffAccount::factory()->withRole(SystemStaffRole::WarehouseWorker)->create();

        $this->actingAs($warehouseWorker)->post(route('panel.orders.returns.store', $orderId), [
            'lines' => [['order_line_id' => $lineId, 'quantity' => '1', 'reason_id' => null, 'reason_comment' => null]],
        ])->assertForbidden();
    }
}
