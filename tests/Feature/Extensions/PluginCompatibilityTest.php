<?php

declare(strict_types=1);

namespace Tests\Feature\Extensions;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\Events\OrderPlaced;
use Litecommerce\Modules\Payments\Contracts\Events\PaymentConfirmed;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentAvailabilityContext;
use Litecommerce\Modules\Payments\Contracts\PaymentContext;
use Litecommerce\Modules\Payments\Contracts\PaymentMethod;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodDescriptor;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Contracts\PaymentStartResult;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Contracts\VerifiedPaymentNotification;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Plugin/theme compatibility contract: a plugin hook may fail without turning a
 * committed fact into an error, and a failing payment hook never yields a paid checkout.
 */
final class PluginCompatibilityTest extends TestCase
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
    }

    public function test_a_failing_committed_event_listener_does_not_block_later_listeners(): void
    {
        $delivered = [];
        Event::listen(OrderPlaced::class, static function (): void {
            throw new \RuntimeException('plugin listener exploded');
        });
        Event::listen(OrderPlaced::class, static function (OrderPlaced $event) use (&$delivered): void {
            $delivered[] = $event->orderId;
        });
        Log::spy();

        CommittedEvent::dispatch(new OrderPlaced('order-1'));

        $this->assertSame(['order-1'], $delivered);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_checkout_survives_a_throwing_order_placed_listener_and_keeps_the_order(): void
    {
        $seen = [];
        Event::listen(OrderPlaced::class, static function (): void {
            throw new \RuntimeException('plugin listener exploded');
        });
        Event::listen(OrderPlaced::class, static function (OrderPlaced $event) use (&$seen): void {
            $seen[] = $event->orderId;
        });

        $response = $this->checkoutDigitalCart('core.bank_transfer');

        $response->assertOk();
        $orderId = (string) $response->json('order.id');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertSame([$orderId], $seen);
    }

    public function test_a_payment_method_that_fails_to_start_never_yields_a_paid_or_redirecting_checkout(): void
    {
        $confirmed = false;
        Event::listen(PaymentConfirmed::class, static function () use (&$confirmed): void {
            $confirmed = true;
        });
        $this->bankAccount();
        app(PaymentMethodRegistry::class)->register(new ExplodingStartPaymentMethod);

        $response = $this->checkoutDigitalCart('test.exploding');

        $response->assertOk()
            ->assertJsonPath('payment.status', PaymentAttemptStatus::Failed->value)
            ->assertJsonPath('payment.redirect_url', null);
        $orderId = (string) $response->json('order.id');
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('payment_attempts', ['order_id' => $orderId, 'status' => 'failed', 'failure_code' => 'initiation_failed']);
        $this->assertFalse($confirmed);
    }

    public function test_a_payment_method_whose_availability_check_throws_creates_no_order(): void
    {
        $this->bankAccount();
        app(PaymentMethodRegistry::class)->register(new ExplodingAvailabilityPaymentMethod);

        $response = $this->checkoutDigitalCart('test.exploding_availability');

        $response->assertStatus(500);
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('payment_attempts')->count());
    }

    private function bankAccount(): void
    {
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Test', 'account_number' => 'PL1', 'instructions' => json_encode(['pl' => 'x']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function checkoutDigitalCart(string $paymentMethodId): TestResponse
    {
        if (! DB::table('bank_transfer_accounts')->exists()) {
            $this->bankAccount();
        }
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->post(route('panel.catalog.products.store'), [
            'locale' => 'pl', 'name' => 'Ebook', 'short_description' => '', 'description_html' => '',
            'type' => 'digital', 'status' => 'published', 'featured' => false, 'unit_code' => 'piece',
            'minimum_quantity' => '1', 'maximum_quantity' => '1', 'quantity_step' => '1',
            'sku' => '', 'gtin' => '', 'mpn' => '', 'brand_id' => '', 'category_ids' => [], 'primary_category_id' => '',
        ])->assertSessionHasNoErrors();
        $productId = (int) DB::table('products')->latest('id')->value('id');
        $variantId = (int) DB::table('product_variants')->where('product_id', $productId)->value('id');
        $this->actingAs($staff)->put(route('panel.catalog.products.variants.price.update', [$productId, $variantId]), ['regular_amount' => '10.00', 'regular_mode' => 'gross'])->assertSessionHasNoErrors();
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

        return $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => $paymentMethodId]);
    }
}

abstract class BaseTestPaymentMethod implements PaymentMethod
{
    abstract protected function id(): string;

    public function descriptor(): PaymentMethodDescriptor
    {
        return new PaymentMethodDescriptor($this->id(), 'test-provider', 'Test', 'online');
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        return true;
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        return new PaymentStartResult('tx-'.$context->orderId, PaymentAttemptStatus::Pending, null);
    }

    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification
    {
        throw new \LogicException('Not used.');
    }

    public function query(string $providerTransactionId): PaymentStatusResult
    {
        return new PaymentStatusResult(PaymentAttemptStatus::Pending);
    }

    public function cancel(string $providerTransactionId): void {}
}

final class ExplodingStartPaymentMethod extends BaseTestPaymentMethod
{
    protected function id(): string
    {
        return 'test.exploding';
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        throw new \RuntimeException('provider down');
    }
}

final class ExplodingAvailabilityPaymentMethod extends BaseTestPaymentMethod
{
    protected function id(): string
    {
        return 'test.exploding_availability';
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        throw new \RuntimeException('availability check crashed');
    }
}
