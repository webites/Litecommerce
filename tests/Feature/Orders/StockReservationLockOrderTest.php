<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\OrderPlacementLineData;
use Litecommerce\Modules\Orders\Contracts\OrderPlacer;
use Litecommerce\Modules\Orders\Contracts\PlaceOrderData;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Regression for a real MySQL deadlock a load test found (docs/knowledge-base/
 * 10-operations.md "Testy obciążeniowe"): the original hypothesis was that two concurrent
 * orders sharing the same two physical variants, added to their carts in opposite order,
 * would lock their stock_items rows in opposite order too — a classic circular-wait
 * deadlock. Verifying THAT hypothesis with an HTTP-level test (cart -> checkout -> confirm)
 * turned out to prove nothing either way: `cart_items` has a UNIQUE(cart_id, variant_id)
 * index, and both MySQL and SQLite already use it to satisfy `WHERE cart_id = ?`, which
 * returns rows pre-sorted by variant_id regardless of insertion order (confirmed with
 * EXPLAIN against the real MySQL container) — so PlaceOrder always received its lines
 * already sorted before this fix existed, on that specific path. The fix in
 * PlaceOrder::place() (explicit `usort` by variant_id before reserving) is kept anyway as
 * defence-in-depth — it must not depend on that incidental index behaviour surviving
 * unrelated future changes — so this test bypasses Cart/Checkout entirely and calls
 * OrderPlacer::place() directly with a hand-built, deliberately reverse-ordered line list,
 * which #is# something an HTTP-level test cannot control.
 */
final class StockReservationLockOrderTest extends TestCase
{
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        DB::table('languages')->insert(['code' => 'pl', 'name' => 'Polski', 'is_default' => true, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('shop_currencies')->insert(['code' => 'PLN', 'name' => 'Złoty', 'symbol' => 'zł', 'decimal_places' => 2, 'is_base' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_place_order_reserves_stock_in_ascending_variant_id_order_regardless_of_line_order(): void
    {
        $staff = StaffAccount::factory()->create();
        $variantIds = [];

        foreach (['Kubek A', 'Kubek B'] as $name) {
            $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
                'locale' => 'pl', 'name' => $name, 'short_description' => '', 'description_html' => '',
                'type' => 'physical', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
                'minimum_quantity' => '1', 'maximum_quantity' => '', 'quantity_step' => '1', 'sku' => '', 'gtin' => '',
                'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
            ])->assertSessionHasNoErrors();
            $variantId = (int) DB::table('product_variants')->latest('id')->value('id');
            $this->actingAs($staff)->post(route('panel.warehouse.variants.receive', $variantId), ['quantity' => '5'])->assertSessionHasNoErrors();
            $variantIds[] = $variantId;
        }
        $this->app['auth']->forgetGuards();

        [$lowerId, $higherId] = $variantIds;
        self::assertLessThan($higherId, $lowerId, 'Test setup assumption: sequential inserts get ascending ids.');

        $line = fn (int $variantId): OrderPlacementLineData => new OrderPlacementLineData(
            variantId: $variantId, productType: 'physical', sku: 'SKU-'.$variantId, displayName: 'Test',
            quantity: '1', unitAmount: '10.00', lineNetAmount: '10.00', lineVatAmount: '0.00', lineGrossAmount: '10.00',
        );

        // Deliberately handed in HIGHER-id-first order — an HTTP-driven cart can't force
        // this (see class docblock), but nothing stops PlaceOrder's caller from doing so,
        // and a real deadlock only needs ONE of the two racing transactions to see this
        // order to create the circular wait against another one that sees the opposite.
        $data = new PlaceOrderData(
            sourceType: 'test', sourceId: (string) Str::uuid(), customerAccountId: null,
            buyerType: 'person', email: 'buyer@example.com', companyName: null, taxIdentifier: null,
            registrationNumber: null, phone: null, customerNotes: null,
            billingAddress: ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            shippingAddress: null, locale: 'pl', currencyCode: 'PLN',
            paymentMethodId: 'core.bank_transfer', paymentKind: 'bank_transfer',
            totalNetAmount: '20.00', totalVatAmount: '0.00', totalGrossAmount: '20.00',
            lines: [$line($higherId), $line($lowerId)],
        );

        app(OrderPlacer::class)->place($data);

        $reservedVariantIdsInReservationOrder = DB::table('stock_reservations')
            ->join('stock_items', 'stock_items.id', '=', 'stock_reservations.stock_item_id')
            ->orderBy('stock_reservations.id')
            ->pluck('stock_items.variant_id')
            ->all();

        self::assertSame([$lowerId, $higherId], $reservedVariantIdsInReservationOrder);
    }
}
