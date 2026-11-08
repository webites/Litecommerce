<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Contracts\PaymentExpiryReconciler;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Deterministic interleavings of the contended operations. Real parallelism against MySQL is exercised by the load tests.
 */
final class ContentionTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
    }

    public function test_two_shoppers_racing_for_the_last_unit_cannot_both_buy_it(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createPhysicalVariant($staff, '10.00', stock: '1');

        $this->startNewShopper();
        $cartA = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cartA);
        $draftA = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->prepareDraft($draftA);
        $hashA = (string) $this->getJson("/checkout/{$draftA}")->json('price.hash');
        $cookiesA = [$this->defaultCookies, $this->unencryptedCookies];

        $this->startNewShopper();
        $cartB = $this->postJson('/cart/items', ['variant_id' => $variantId, 'quantity' => '1'])->assertOk();
        $this->carryCookiesFrom($cartB);
        $draftB = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->prepareDraft($draftB);
        $hashB = (string) $this->getJson("/checkout/{$draftB}")->json('price.hash');
        $cookiesB = [$this->defaultCookies, $this->unencryptedCookies];

        [$this->defaultCookies, $this->unencryptedCookies] = $cookiesA;
        $this->postJson("/checkout/{$draftA}/confirm", ['price_hash' => $hashA, 'payment_method_id' => 'core.bank_transfer'])->assertOk();

        [$this->defaultCookies, $this->unencryptedCookies] = $cookiesB;
        $this->postJson("/checkout/{$draftB}/confirm", ['price_hash' => $hashB, 'payment_method_id' => 'core.bank_transfer'])
            ->assertStatus(422)->assertJsonPath('code', 'items_unavailable');

        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('stock_reservations')->count());
    }

    public function test_running_the_expiry_sweep_twice_releases_and_cancels_exactly_once(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createPhysicalVariant($staff, '10.00', stock: '2');
        $this->startNewShopper();
        [$response] = $this->checkoutVariant($variantId, '1');
        $orderId = (string) $response->assertOk()->json('order.id');
        DB::table('orders')->where('id', $orderId)->update(['payment_deadline_at' => now()->subMinutes(5)]);

        $first = app(PaymentExpiryReconciler::class)->reconcileDue(50);
        $second = app(PaymentExpiryReconciler::class)->reconcileDue(50);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled']);
        $this->assertSame(1, DB::table('order_status_history')->where('order_id', $orderId)->where('new_status', 'cancelled')->count());
    }

    public function test_paying_an_order_that_was_already_paid_does_not_double_fulfil(): void
    {
        $staff = StaffAccount::factory()->create();
        [, $variantId] = $this->createPhysicalVariant($staff, '10.00', stock: '2');
        $this->startNewShopper();
        [$response] = $this->checkoutVariant($variantId, '1');
        $orderId = (string) $response->assertOk()->json('order.id');

        $this->payOrder($orderId, $staff);
        $this->payOrder($orderId, $staff);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
        $this->assertSame(1, DB::table('stock_reservations')->where('state', 'confirmed')->count());
    }

    private function prepareDraft(string $draftId): void
    {
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->pickupMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();
    }
}
