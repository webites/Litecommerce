<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\Events\OrderStatusChanged;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * ManageOrder::changeStatus() previously wrote only to the internal,
 * unconsumed order_outbox_events table — no plugin could react to a status
 * transition. This is the first real hook (mirrors the existing
 * OrderPlaced convention), added for P1-016's invoice plugin but usable by
 * any future plugin.
 */
final class OrderStatusChangeEventTest extends TestCase
{
    use CarriesTestCookies;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->withCredentials();

        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Test', 'account_number' => 'PL1', 'instructions' => json_encode(['pl' => 'x']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_changing_status_dispatches_order_status_changed(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $orderId = $this->placeDigitalOrder();
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.orders.status.update', $orderId), [
            'status' => 'processing',
        ])->assertSessionHasNoErrors();

        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event): bool => $event->orderId === $orderId && $event->status === 'processing');
    }

    public function test_a_no_op_status_change_does_not_dispatch(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $orderId = $this->placeDigitalOrder();
        $staff = StaffAccount::factory()->create();

        // Freshly placed orders start pending_payment — setting the same status again is a no-op.
        $this->actingAs($staff)->put(route('panel.orders.status.update', $orderId), [
            'status' => 'pending_payment',
        ])->assertSessionHasNoErrors();

        Event::assertNotDispatched(OrderStatusChanged::class);
    }

    public function test_reopening_a_cancelled_order_requires_explicit_confirmation(): void
    {
        $orderId = $this->placeDigitalOrder();
        $staff = StaffAccount::factory()->create();
        app(OrderPaymentManager::class)->cancelUnpaid($orderId, 'Rezygnacja klienta');

        $this->actingAs($staff)->put(route('panel.orders.status.update', $orderId), ['status' => 'processing'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled']);

        $this->actingAs($staff)->put(route('panel.orders.status.update', $orderId), ['status' => 'processing', 'confirm_reopen' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'processing']);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $orderId, 'old_status' => 'cancelled', 'new_status' => 'processing']);
    }

    private function placeDigitalOrder(): string
    {
        $productStaff = StaffAccount::factory()->create();
        $this->actingAs($productStaff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $this->actingAs($productStaff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
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

        return (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');
    }
}
