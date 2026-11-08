<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Payments\Application\ApplyPaymentResult;
use Litecommerce\Modules\Payments\Contracts\Events\PaymentAttemptFailed;
use Litecommerce\Modules\Payments\Contracts\Events\PaymentConfirmed;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * P2-002: ApplyPaymentResult is the single convergence point for manual
 * confirmation, webhooks and expiry reconciliation — covers that both new
 * plugin-facing events dispatch from there, mirroring the existing
 * OrderPlaced/OrderStatusChanged convention.
 */
final class PaymentEventsTest extends TestCase
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

    public function test_manual_confirmation_dispatches_payment_confirmed(): void
    {
        Event::fake([PaymentConfirmed::class]);
        $orderId = $this->placeDigitalOrder();
        $staff = StaffAccount::factory()->create();

        app(PaymentConfirmation::class)->confirmManual($orderId, 'BANK-1', new \DateTimeImmutable, (string) $staff->getKey());

        Event::assertDispatched(PaymentConfirmed::class, fn (PaymentConfirmed $event): bool => $event->orderId === $orderId
            && $event->amount === '10.000000' && $event->currencyCode === 'PLN');
    }

    public function test_a_failed_result_dispatches_payment_attempt_failed(): void
    {
        Event::fake([PaymentAttemptFailed::class]);
        $orderId = $this->placeDigitalOrder();
        $attemptId = (string) DB::table('payment_attempts')->where('order_id', $orderId)->value('id');

        app(ApplyPaymentResult::class)->execute($attemptId, new PaymentStatusResult(
            PaymentAttemptStatus::Failed,
            failureCode: 'card_declined',
            failureMessage: 'Card declined',
        ));

        Event::assertDispatched(PaymentAttemptFailed::class, fn (PaymentAttemptFailed $event): bool => $event->orderId === $orderId
            && $event->paymentAttemptId === $attemptId
            && $event->failureCode === 'card_declined' && $event->failureMessage === 'Card declined');
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
