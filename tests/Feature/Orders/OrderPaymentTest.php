<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\SystemStaffRole;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Orders\Contracts\OrderPaymentManager;
use Litecommerce\Modules\Payments\Application\StartPaymentAttempt;
use Litecommerce\Modules\Payments\Contracts\InvalidPaymentNotificationException;
use Litecommerce\Modules\Payments\Contracts\PaymentAttemptStatus;
use Litecommerce\Modules\Payments\Contracts\PaymentAvailabilityContext;
use Litecommerce\Modules\Payments\Contracts\PaymentConfirmation;
use Litecommerce\Modules\Payments\Contracts\PaymentContext;
use Litecommerce\Modules\Payments\Contracts\PaymentExpiryReconciler;
use Litecommerce\Modules\Payments\Contracts\PaymentMethod;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodDescriptor;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;
use Litecommerce\Modules\Payments\Contracts\PaymentStartResult;
use Litecommerce\Modules\Payments\Contracts\PaymentStatusResult;
use Litecommerce\Modules\Payments\Contracts\VerifiedPaymentNotification;
use Litecommerce\Modules\Payments\Infrastructure\Models\DeferredPaymentWebhook;
use Litecommerce\Modules\Payments\Infrastructure\Models\PaymentAttempt;
use Litecommerce\Modules\Scheduler\Application\RunDueScheduledTasks;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationState;
use Tests\Concerns\CarriesTestCookies;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class OrderPaymentTest extends TestCase
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
        DB::table('bank_transfer_accounts')->insert(['currency_code' => 'PLN', 'beneficiary_name' => 'Litecommerce Test', 'account_number' => 'PL001234', 'instructions' => json_encode(['pl' => 'Tytułem numer zamówienia.']), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('warehouses')->insert(['name' => 'Magazyn główny', 'created_at' => now(), 'updated_at' => now()]);
        $this->deliveryMethodId = (int) DB::table('delivery_methods')->insertGetId([
            'kind' => 'pickup', 'name' => 'Odbiór osobisty', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pickupPointId = (int) DB::table('delivery_pickup_points')->insertGetId([
            'delivery_method_id' => $this->deliveryMethodId, 'name' => 'Sklep', 'country_code' => 'PL',
            'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->withCredentials();
    }

    public function test_checkout_atomically_creates_one_order_snapshot_reservation_and_attempt(): void
    {
        [$orderId, $variantId, $draftId, $hash] = $this->placeOrder();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'source_id' => $draftId, 'status' => 'pending_payment', 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('order_lines', ['order_id' => $orderId, 'variant_id' => $variantId, 'product_type' => 'physical', 'display_name' => 'Kubek testowy']);
        $this->assertDatabaseHas('stock_reservations', ['reference_type' => 'order_line', 'state' => StockReservationState::Pending->value]);
        $this->assertDatabaseHas('payment_attempts', ['id' => $orderId, 'order_id' => $orderId, 'status' => 'pending']);
        $this->assertDatabaseHas('order_outbox_events', ['order_id' => $orderId, 'event_type' => 'OrderPlaced']);

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash])->assertOk()->assertJsonPath('order.id', $orderId);
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('stock_reservations')->count());
        $this->assertSame(1, DB::table('payment_attempts')->count());
    }

    public function test_manual_full_payment_is_idempotent_and_confirms_stock_reservation(): void
    {
        [$orderId] = $this->placeOrder();
        $confirmation = app(PaymentConfirmation::class);
        $paidAt = new \DateTimeImmutable('2026-09-15T10:00:00+02:00');

        $first = $confirmation->confirmManual($orderId, 'BANK-123', $paidAt, '1');
        $second = $confirmation->confirmManual($orderId, 'BANK-123', $paidAt, '1');

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'processing', 'payment_status' => 'paid', 'paid_attempt_id' => $first->id]);
        $this->assertDatabaseHas('stock_reservations', ['state' => StockReservationState::Confirmed->value]);
        $this->assertSame(1, DB::table('payment_attempts')->count());
        $this->assertSame(1, DB::table('order_outbox_events')->where('event_type', 'PaymentConfirmed')->count());
    }

    public function test_payment_after_cancellation_is_flagged_late_without_restoring_stock_hold(): void
    {
        [$orderId] = $this->placeOrder();
        $manager = app(OrderPaymentManager::class);
        $manager->cancelUnpaid($orderId, 'Rezygnacja klienta');

        app(PaymentConfirmation::class)->confirmManual($orderId, 'LATE-1', new \DateTimeImmutable, '1');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled', 'payment_status' => 'late_payment']);
        $this->assertDatabaseHas('stock_reservations', ['state' => StockReservationState::Released->value]);
        $this->assertDatabaseHas('order_outbox_events', ['order_id' => $orderId, 'event_type' => 'LatePaymentReceived']);
    }

    public function test_second_distinct_success_is_an_overpayment_and_does_not_duplicate_fulfilment(): void
    {
        [$orderId] = $this->placeOrder();
        $confirmation = app(PaymentConfirmation::class);
        $confirmation->confirmManual($orderId, 'PAY-1', new \DateTimeImmutable, '1');
        $second = $confirmation->confirmManual($orderId, 'PAY-2', new \DateTimeImmutable, '1');

        $this->assertSame('overpayment', $second->status->value);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'overpaid']);
        $this->assertSame(1, DB::table('order_status_history')->where('new_status', 'processing')->count());
    }

    public function test_panel_has_a_separate_order_detail_and_permission_gate(): void
    {
        [$orderId] = $this->placeOrder();
        $administrator = StaffAccount::factory()->create();
        $this->actingAs($administrator)->get(route('panel.orders.show', $orderId))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Panel/Orders/Show')
                ->where('order.id', $orderId)->where('order.lines.0.display_name', 'Kubek testowy')
                ->where('order.payment_method_name', trans('payments::methods.bank_transfer'))
                ->where('order.payment_attempts.0.method_name', trans('payments::methods.bank_transfer'))
                ->has('order.payment_attempts', 1));

        $editor = StaffAccount::factory()->withRole(SystemStaffRole::ContentEditor)->create();
        $this->actingAs($editor)->get(route('panel.orders.show', $orderId))->assertForbidden();
    }

    public function test_a_payment_method_that_is_no_longer_registered_is_labelled_by_its_id(): void
    {
        $registry = app(PaymentMethodRegistry::class);

        $this->assertSame(trans('payments::methods.bank_transfer'), $registry->nameOf('core.bank_transfer'));
        $this->assertSame('removed-plugin.card', $registry->nameOf('removed-plugin.card'));
    }

    public function test_the_payment_method_name_is_a_snapshot_of_the_attempt(): void
    {
        [$orderId] = $this->placeOrder();
        $this->assertSame(trans('payments::methods.bank_transfer'), DB::table('payment_attempts')->where('order_id', $orderId)->value('method_name'));

        DB::table('payment_attempts')->where('order_id', $orderId)->update(['method_id' => 'removed-plugin.card', 'method_name' => 'Karta (wtyczka)']);

        $administrator = StaffAccount::factory()->create();
        $this->actingAs($administrator)->get(route('panel.orders.show', $orderId))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.payment_attempts.0.method_name', 'Karta (wtyczka)'));
    }

    public function test_merchant_can_configure_the_bank_account_per_currency(): void
    {
        $administrator = StaffAccount::factory()->create();
        $this->actingAs($administrator)->get(route('panel.settings.tabs.show', 'payments'))
            ->assertOk()
            ->assertJsonPath('component', 'payments')
            ->assertJsonPath('props.currencies.0.code', 'PLN')
            ->assertJsonPath('props.currencies.0.account_number', 'PL001234');

        $this->actingAs($administrator)->put(route('panel.payment-settings.update', 'PLN'), [
            'beneficiary_name' => 'Nowy sklep', 'account_number' => 'PL009999',
            'instructions' => ['pl' => 'Wpisz UUID zamówienia.'], 'enabled' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bank_transfer_accounts', ['currency_code' => 'PLN', 'beneficiary_name' => 'Nowy sklep', 'account_number' => 'PL009999']);
    }

    public function test_verified_webhook_is_idempotent_and_confirms_payment(): void
    {
        app(PaymentMethodRegistry::class)->register(new FakeOnlinePaymentMethod);
        [$orderId] = $this->placeOrder('test.online');
        $body = json_encode(['transaction_id' => 'online:'.$orderId, 'status' => 'succeeded'], JSON_THROW_ON_ERROR);

        $this->call('POST', '/payments/webhooks/test.online', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'valid',
        ], $body)->assertNoContent();
        $this->call('POST', '/payments/webhooks/test.online', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'valid',
        ], $body)->assertNoContent();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
        $this->assertSame(1, DB::table('payment_notifications')->count());
        $this->assertSame(1, DB::table('order_outbox_events')->where('event_type', 'PaymentConfirmed')->count());
    }

    public function test_a_webhook_whose_processing_failed_is_processed_when_the_provider_retries(): void
    {
        [$orderId, $body] = $this->onlineOrderWebhook();
        $failures = 1;
        PaymentAttempt::updating(function () use (&$failures): void {
            if ($failures-- > 0) {
                throw new \RuntimeException('Simulated deadlock.');
            }
        });

        $this->postWebhook($body)->assertStatus(500);
        $this->assertSame(0, DB::table('payment_notifications')->count());
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);

        $this->postWebhook($body)->assertNoContent();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
        $this->assertSame(1, DB::table('payment_notifications')->count());
    }

    public function test_a_late_pending_notification_does_not_reopen_a_failed_attempt(): void
    {
        [$orderId] = $this->onlineOrderWebhook();
        $transaction = 'online:'.$orderId;

        $this->postWebhook(json_encode(['transaction_id' => $transaction, 'status' => 'failed'], JSON_THROW_ON_ERROR))->assertNoContent();
        $this->postWebhook(json_encode(['transaction_id' => $transaction, 'status' => 'pending'], JSON_THROW_ON_ERROR))->assertNoContent();

        $this->assertDatabaseHas('payment_attempts', ['provider_transaction_id' => $transaction, 'status' => 'failed']);

        $this->postWebhook(json_encode(['transaction_id' => $transaction, 'status' => 'succeeded'], JSON_THROW_ON_ERROR))->assertNoContent();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
    }

    public function test_webhook_rejects_an_invalid_signature_without_persisting_payload(): void
    {
        app(PaymentMethodRegistry::class)->register(new FakeOnlinePaymentMethod);
        [$orderId] = $this->placeOrder('test.online');
        $body = json_encode(['transaction_id' => 'online:'.$orderId, 'status' => 'succeeded'], JSON_THROW_ON_ERROR);

        $this->call('POST', '/payments/webhooks/test.online', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => 'invalid',
        ], $body)->assertUnauthorized();

        $this->assertSame(0, DB::table('payment_notifications')->count());
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
    }

    /** @return array{string, string} order id and the webhook body */
    private function onlineOrderWebhook(): array
    {
        app(PaymentMethodRegistry::class)->register(new FakeOnlinePaymentMethod);
        [$orderId] = $this->placeOrder('test.online');

        return [$orderId, json_encode(['transaction_id' => 'online:'.$orderId, 'status' => 'succeeded'], JSON_THROW_ON_ERROR)];
    }

    private function postWebhook(string $body, string $signature = 'valid'): TestResponse
    {
        return $this->call('POST', '/payments/webhooks/test.online', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }

    public function test_a_webhook_during_maintenance_is_stored_encrypted_and_not_processed(): void
    {
        [$orderId, $body] = $this->onlineOrderWebhook();
        app(MaintenanceMode::class)->enable(MaintenanceReason::Update);

        $this->postWebhook($body)->assertStatus(202);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertSame(0, DB::table('payment_notifications')->count());
        $row = DB::table('deferred_payment_webhooks')->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame('test.online', $row->method_id);
        $this->assertStringNotContainsString('online:'.$orderId, $row->body);
        $this->assertStringNotContainsString('valid', $row->headers);
        $this->assertSame($body, DeferredPaymentWebhook::query()->firstOrFail()->body);
    }

    public function test_deferred_webhooks_are_replayed_only_after_maintenance_ends_and_only_once(): void
    {
        [$orderId, $body] = $this->onlineOrderWebhook();
        $maintenance = app(MaintenanceMode::class);
        $maintenance->enable(MaintenanceReason::Update);
        $this->postWebhook($body)->assertStatus(202);
        $this->postWebhook($body)->assertStatus(202);

        $this->artisan('payments:replay-deferred-webhooks')->assertSuccessful();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertSame(2, DB::table('deferred_payment_webhooks')->where('status', 'pending')->count());

        $maintenance->disable();
        $this->artisan('payments:replay-deferred-webhooks')->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
        $this->assertSame(2, DB::table('deferred_payment_webhooks')->where('status', 'processed')->count());
        $this->assertSame(1, DB::table('payment_notifications')->count());
        $this->assertSame(1, DB::table('order_outbox_events')->where('event_type', 'PaymentConfirmed')->count());

        $this->artisan('payments:replay-deferred-webhooks')->assertSuccessful();
        $this->assertSame(1, DB::table('payment_notifications')->count());
    }

    public function test_a_deferred_webhook_with_a_bad_signature_is_rejected_at_replay_without_effect(): void
    {
        [$orderId, $body] = $this->onlineOrderWebhook();
        app(MaintenanceMode::class)->enable(MaintenanceReason::Manual);
        $this->postWebhook($body, 'forged')->assertStatus(202);
        app(MaintenanceMode::class)->disable();

        $this->artisan('payments:replay-deferred-webhooks')->assertSuccessful();

        $this->assertDatabaseHas('deferred_payment_webhooks', ['status' => 'rejected']);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertSame(0, DB::table('payment_notifications')->count());
    }

    public function test_deferred_ingress_refuses_oversized_bodies_and_a_full_inbox(): void
    {
        app(MaintenanceMode::class)->enable(MaintenanceReason::Update);

        config(['payments.deferred_webhook_max_body_bytes' => 10]);
        $this->postWebhook(str_repeat('x', 11))->assertStatus(413);

        config(['payments.deferred_webhook_max_body_bytes' => 65536, 'payments.deferred_webhook_max_pending' => 1]);
        $this->postWebhook('{}')->assertStatus(202);
        $this->postWebhook('{}')->assertStatus(503)->assertHeader('Retry-After');
        $this->assertSame(1, DB::table('deferred_payment_webhooks')->count());
    }

    public function test_a_deferred_webhook_for_an_unknown_attempt_or_method_is_closed_without_effect(): void
    {
        app(PaymentMethodRegistry::class)->register(new FakeOnlinePaymentMethod);
        $make = fn (string $method) => DeferredPaymentWebhook::query()->create([
            'id' => (string) Str::uuid(), 'method_id' => $method, 'body' => '{"transaction_id":"online:missing","status":"succeeded"}',
            'headers' => ['x-signature' => 'valid'], 'status' => 'pending', 'received_at' => now(),
        ]);
        $unknownAttempt = $make('test.online');
        $unknownMethod = $make('nobody.knows');

        $this->artisan('payments:replay-deferred-webhooks')->assertSuccessful();

        $this->assertSame('failed', $unknownAttempt->fresh()->status);
        $this->assertSame('rejected', $unknownMethod->fresh()->status);
        $this->assertSame(0, DB::table('payment_notifications')->count());
    }

    public function test_finished_deferred_webhooks_are_pruned_after_retention_and_pending_ones_never(): void
    {
        $make = fn (string $status, string $finished) => DeferredPaymentWebhook::query()->create([
            'id' => (string) Str::uuid(), 'method_id' => 'test.online', 'body' => '{}', 'headers' => [], 'status' => $status,
            'received_at' => now()->subDays(100), 'finished_at' => $status === 'pending' ? null : now()->subDays((int) $finished),
        ]);
        $make('processed', '40');
        $make('processed', '5');
        $make('pending', '0');

        $this->artisan('payments:replay-deferred-webhooks')->assertSuccessful();

        $this->assertSame(2, DB::table('deferred_payment_webhooks')->count());
        $this->assertContains('core.payments.replay_deferred_webhooks', array_map(static fn ($task): string => $task->key, app(ScheduledTaskRegistry::class)->all()));
    }

    public function test_uncertain_online_payment_gets_one_extension_then_manual_review(): void
    {
        app(PaymentMethodRegistry::class)->register(new FakeOnlinePaymentMethod);
        [$orderId] = $this->placeOrder('test.online');
        DB::table('orders')->where('id', $orderId)->update(['payment_deadline_at' => now()->subMinute()]);

        $reconciler = app(PaymentExpiryReconciler::class);
        $this->assertSame(1, $reconciler->reconcileDue());
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'deadline_extended' => true, 'payment_status' => 'unpaid']);

        DB::table('orders')->where('id', $orderId)->update(['payment_deadline_at' => now()->subMinute()]);
        $this->assertSame(1, $reconciler->reconcileDue());
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'payment_review', 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('stock_reservations', ['state' => StockReservationState::Released->value]);
    }

    public function test_scheduler_cancels_an_overdue_unpaid_order_and_releases_its_stock_hold(): void
    {
        [$orderId] = $this->placeOrder();
        DB::table('orders')->where('id', $orderId)->update(['payment_deadline_at' => now()->subMinute()]);

        app(RunDueScheduledTasks::class)->run(20, 10);

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled', 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('stock_reservations', ['state' => StockReservationState::Released->value]);
        $this->assertDatabaseHas('scheduled_tasks', ['key' => 'core.payments.reconcile_expired', 'run_count' => 1, 'consecutive_failures' => 0]);
    }

    public function test_reconciliation_survives_an_order_whose_stock_reservation_is_missing(): void
    {
        [$orderId] = $this->placeOrder();
        DB::table('orders')->where('id', $orderId)->update(['payment_deadline_at' => now()->subMinute()]);
        DB::table('stock_reservations')->delete();

        $processed = app(PaymentExpiryReconciler::class)->reconcileDue();

        $this->assertSame(0, $processed);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'unpaid']);
        $this->assertDatabaseMissing('orders', ['id' => $orderId, 'status' => 'cancelled']);
    }

    /** @return array{string, int, string, string} */
    public function test_orders_get_sequential_readable_numbers_and_the_prefix_is_a_snapshot(): void
    {
        [$first] = $this->placeOrder();
        $staff = StaffAccount::factory()->create();
        $this->actingAs($staff)->put(route('panel.settings.orders.update'), ['number_prefix' => 'ZAM/'])->assertSessionHasNoErrors();
        $this->app['auth']->forgetGuards();
        [$second] = $this->placeOrder();

        $firstRow = DB::table('orders')->where('id', $first)->first();
        $secondRow = DB::table('orders')->where('id', $second)->first();
        $this->assertSame(1001, (int) $firstRow->number);
        $this->assertSame('1001', $firstRow->public_number);
        $this->assertSame(1002, (int) $secondRow->number);
        $this->assertSame('ZAM/1002', $secondRow->public_number);
        $this->assertDatabaseHas('audit_entries', ['action' => 'orders.settings.updated']);

        $transferTitle = DB::table('payment_attempts')->where('order_id', $second)->value('metadata');
        $this->assertStringContainsString('ZAM\/1002', (string) $transferTitle);
    }

    public function test_payment_cannot_be_retried_for_a_cancelled_order(): void
    {
        [$orderId] = $this->placeOrder();
        app(OrderPaymentManager::class)->cancelUnpaid($orderId, 'Rezygnacja klienta');

        $this->expectException(\DomainException::class);
        app(StartPaymentAttempt::class)->retry($orderId, 'buyer@example.com');
    }

    public function test_a_cancelled_order_is_not_pulled_back_into_payment_review(): void
    {
        [$orderId] = $this->placeOrder();
        $manager = app(OrderPaymentManager::class);
        $manager->cancelUnpaid($orderId, 'Rezygnacja klienta');

        $manager->markForPaymentReview($orderId, 'Niepewny wynik');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancelled']);
    }

    public function test_the_order_number_prefix_rejects_unsafe_values(): void
    {
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->put(route('panel.settings.orders.update'), ['number_prefix' => 'TOO-LONG-PREFIX'])->assertSessionHasErrors('number_prefix');
        $this->actingAs($staff)->put(route('panel.settings.orders.update'), ['number_prefix' => '<b>'])->assertSessionHasErrors('number_prefix');
    }

    public function test_the_panel_order_list_finds_orders_by_number_and_exposes_it(): void
    {
        [$orderId] = $this->placeOrder();
        $staff = StaffAccount::factory()->create();

        $this->actingAs($staff)->get(route('panel.orders.index', ['q' => '1001']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('orders.data.0.id', $orderId)->where('orders.data.0.number', '1001')->where('filters.q', '1001'));
        $this->actingAs($staff)->get(route('panel.orders.index', ['q' => '9999']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('orders.data', 0));
    }

    private function placeOrder(string $paymentMethodId = 'core.bank_transfer'): array
    {
        $staff = StaffAccount::factory()->create();
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
        $response = $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => $paymentMethodId])->assertOk();

        return [(string) $response->json('order.id'), $variantId, $draftId, $hash];
    }
}

final class FakeOnlinePaymentMethod implements PaymentMethod
{
    public function descriptor(): PaymentMethodDescriptor
    {
        return new PaymentMethodDescriptor('test.online', 'test-provider', 'Test online', 'online');
    }

    public function available(PaymentAvailabilityContext $context): bool
    {
        return true;
    }

    public function start(PaymentContext $context): PaymentStartResult
    {
        return new PaymentStartResult('online:'.$context->orderId, PaymentAttemptStatus::Pending, 'https://payments.example.test/'.$context->attemptId);
    }

    public function verifyNotification(string $rawBody, array $headers): VerifiedPaymentNotification
    {
        if (($headers['x-signature'] ?? null) !== 'valid') {
            throw new InvalidPaymentNotificationException('Invalid signature.');
        }
        $payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);

        return new VerifiedPaymentNotification(
            (string) $payload['transaction_id'], PaymentAttemptStatus::from((string) $payload['status']),
            new \DateTimeImmutable, 'notification-1', ['transaction_id' => $payload['transaction_id']],
        );
    }

    public function query(string $providerTransactionId): PaymentStatusResult
    {
        return new PaymentStatusResult(PaymentAttemptStatus::Pending);
    }

    public function cancel(string $providerTransactionId): void {}
}
