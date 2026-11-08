<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Litecommerce\Modules\Email\Infrastructure\Models\EmailMessage;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Orders\Contracts\Events\OrderHandedOver;
use Litecommerce\Modules\Orders\Contracts\Events\OrderPickupExpired;
use Litecommerce\Modules\Orders\Contracts\Events\OrderReadyForPickup;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Orders\Contracts\InvalidFulfillmentTransitionException;
use Litecommerce\Modules\Orders\Contracts\OrderFulfillmentManager;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * "Gotowe do odbioru": a separate fulfillment fact with its own history,
 * independent of the order status (06-fulfillment.md, P2-016b).
 */
final class PickupFulfillmentTest extends TestCase
{
    use PlacesStorefrontOrders;
    use UsesIsolatedStorage;

    private StaffAccount $staff;

    private int $variantId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedStorage();
        $this->artisan('migrate', ['--force' => true]);
        InstallationLock::create();
        $this->setUpShop();
        DB::table('delivery_methods')->where('id', $this->pickupMethodId)->update(['supports_pay_on_pickup' => true]);
        $this->staff = StaffAccount::factory()->create();
        [, $this->variantId] = $this->createPhysicalVariant($this->staff, '10.00', 'gross', '5');
    }

    private function fulfillment(): OrderFulfillmentManager
    {
        return app(OrderFulfillmentManager::class);
    }

    private function placeOrder(string $paymentMethod): string
    {
        $this->startNewShopper();
        [$response] = $this->checkoutVariant($this->variantId, '2', $paymentMethod);

        return (string) $response->assertOk()->json('order.id');
    }

    private function actor(): string
    {
        return (string) $this->staff->getKey();
    }

    private function delivery(string $orderId): object
    {
        return DB::table('order_deliveries')->where('order_id', $orderId)->first();
    }

    private function stock(): object
    {
        return DB::table('stock_items')->where('variant_id', $this->variantId)->first();
    }

    private function inDays(int $days): \DateTimeImmutable
    {
        return new \DateTimeImmutable("+{$days} days");
    }

    public function test_a_paid_prepaid_order_is_marked_ready_emails_the_customer_and_records_the_fact(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);
        $seen = [];
        Event::listen(OrderReadyForPickup::class, static function (OrderReadyForPickup $event) use (&$seen): void {
            $seen[] = $event;
        });

        $this->assertTrue($this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(5)));

        $delivery = $this->delivery($orderId);
        $this->assertSame('ready_for_pickup', $delivery->fulfillment_state);
        $this->assertNotNull($delivery->ready_for_pickup_at);
        $this->assertNotNull($delivery->pickup_deadline_at);
        $this->assertDatabaseHas('order_delivery_events', ['order_id' => $orderId, 'type' => 'ready_for_pickup', 'actor_type' => 'staff', 'actor_id' => $this->actor()]);
        $this->assertDatabaseHas('order_outbox_events', ['order_id' => $orderId, 'event_type' => 'OrderReadyForPickup']);
        $this->assertDatabaseHas('email_messages', ['definition_key' => 'core.order_ready_for_pickup', 'idempotency_key' => "order:{$orderId}:ready_for_pickup"]);
        $this->assertCount(1, $seen);
        $this->assertSame($orderId, $seen[0]->orderId);
        $this->assertSame('processing', DB::table('orders')->where('id', $orderId)->value('status'), 'the order status is untouched');
    }

    public function test_the_email_carries_the_pickup_point_instructions_and_deadline(): void
    {
        DB::table('delivery_pickup_points')->where('id', $this->pickupPointId)->update(['instructions' => json_encode(['pl' => 'Wejście od podwórza'])]);
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);

        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(5));

        $variables = EmailMessage::query()->where('definition_key', 'core.order_ready_for_pickup')->firstOrFail()->variables;
        $this->assertSame('Sklep', $variables['pickup_name']);
        $this->assertSame('Testowa 1, 00-001 Warszawa', $variables['pickup_address']);
        $this->assertSame('Wejście od podwórza', $variables['pickup_instructions']);
        $this->assertNotSame('', $variables['pickup_deadline']);
    }

    public function test_marking_ready_twice_changes_nothing_and_sends_one_email(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);

        $this->assertTrue($this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null));
        $this->assertFalse($this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null));

        $this->assertDatabaseCount('order_delivery_events', 1);
        $this->assertSame(1, DB::table('email_messages')->where('definition_key', 'core.order_ready_for_pickup')->count());
    }

    public function test_an_unpaid_prepaid_order_cannot_be_marked_ready(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');

        try {
            $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null);
            $this->fail('An unpaid prepaid order must be refused.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::PAYMENT_REQUIRED, $exception->reason);
        }
        $this->assertSame('pending', $this->delivery($orderId)->fulfillment_state);
    }

    public function test_a_cancelled_order_and_a_past_deadline_are_refused(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');

        try {
            $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), new \DateTimeImmutable('-1 hour'));
            $this->fail('A past deadline must be refused.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::DEADLINE_IN_PAST, $exception->reason);
        }

        DB::table('orders')->where('id', $orderId)->update(['status' => 'cancelled']);
        try {
            $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null);
            $this->fail('A cancelled order must be refused.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::ORDER_NOT_FULFILLABLE, $exception->reason);
        }
    }

    public function test_a_non_pickup_delivery_cannot_be_marked_ready(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);
        DB::table('order_deliveries')->where('order_id', $orderId)->update(['kind' => 'flat_rate']);

        try {
            $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null);
            $this->fail('Only pickup deliveries can be marked ready.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::NOT_PICKUP, $exception->reason);
        }
    }

    public function test_handing_over_a_prepaid_order_settles_stock_and_is_idempotent(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null);
        $this->assertEquals(5, $this->stock()->on_hand_quantity);
        $seen = [];
        Event::listen(OrderHandedOver::class, static function (OrderHandedOver $event) use (&$seen): void {
            $seen[] = $event;
        });

        $this->assertTrue($this->fulfillment()->confirmHandover($orderId, $this->actor(), null));
        $this->assertFalse($this->fulfillment()->confirmHandover($orderId, $this->actor(), null));

        $this->assertSame('handed_over', $this->delivery($orderId)->fulfillment_state);
        $this->assertNotNull($this->delivery($orderId)->handed_over_at);
        $this->assertEquals(3, $this->stock()->on_hand_quantity, 'on-hand stock decreases exactly once');
        $this->assertEquals(0, $this->stock()->reserved_quantity);
        $this->assertSame(1, DB::table('order_delivery_events')->where('type', 'handed_over')->count());
        $this->assertCount(1, $seen);
    }

    public function test_the_order_must_be_ready_before_it_can_be_handed_over(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);

        try {
            $this->fulfillment()->confirmHandover($orderId, $this->actor(), null);
            $this->fail('A pending delivery cannot be handed over.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::WRONG_STATE, $exception->reason);
        }
        $this->assertEquals(5, $this->stock()->on_hand_quantity);
    }

    public function test_handing_over_a_pay_on_pickup_order_needs_the_payment_reference_and_records_it_atomically(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(3));

        try {
            $this->fulfillment()->confirmHandover($orderId, $this->actor(), null);
            $this->fail('An unpaid pay-on-pickup order needs the payment recorded.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::PAYMENT_REQUIRED, $exception->reason);
        }
        $this->assertSame('unpaid', DB::table('orders')->where('id', $orderId)->value('payment_status'));
        $this->assertSame('ready_for_pickup', $this->delivery($orderId)->fulfillment_state);

        $this->assertTrue($this->fulfillment()->confirmHandover($orderId, $this->actor(), 'KP-17'));

        $order = DB::table('orders')->where('id', $orderId)->first();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('processing', $order->status);
        $this->assertSame('handed_over', $this->delivery($orderId)->fulfillment_state);
        $this->assertEquals(3, $this->stock()->on_hand_quantity);
        $this->assertDatabaseHas('payment_attempts', ['order_id' => $orderId, 'provider_transaction_id' => 'manual:KP-17', 'status' => 'succeeded']);
    }

    public function test_a_payment_reference_used_by_another_order_is_a_form_error_not_a_crash(): void
    {
        $first = $this->placeOrder('core.pay_on_pickup');
        $second = $this->placeOrder('core.pay_on_pickup');
        foreach ([$first, $second] as $orderId) {
            $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(3));
        }
        $this->fulfillment()->confirmHandover($first, $this->actor(), 'KP-DUP');

        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.handover', $second), ['payment_reference' => 'KP-DUP'])
            ->assertSessionHasErrors('payment_reference');

        $this->assertSame('unpaid', DB::table('orders')->where('id', $second)->value('payment_status'));
        $this->assertSame('ready_for_pickup', $this->delivery($second)->fulfillment_state);
    }

    public function test_a_failing_stock_settlement_rolls_back_the_recorded_payment(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(3));
        DB::table('stock_reservations')->update(['state' => 'released']);

        try {
            $this->fulfillment()->confirmHandover($orderId, $this->actor(), 'KP-18');
            $this->fail('A released reservation must block the hand-over.');
        } catch (InvalidFulfillmentTransitionException $exception) {
            $this->assertSame(InvalidFulfillmentTransitionException::STOCK_UNAVAILABLE, $exception->reason);
        }

        $this->assertSame('unpaid', DB::table('orders')->where('id', $orderId)->value('payment_status'));
        $this->assertSame('ready_for_pickup', $this->delivery($orderId)->fulfillment_state);
        $this->assertDatabaseMissing('payment_attempts', ['provider_transaction_id' => 'manual:KP-18', 'status' => 'succeeded']);
    }

    public function test_an_uncollected_pay_on_pickup_order_lapses_after_its_deadline_and_frees_the_stock(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(3));
        $this->assertEquals(2, $this->stock()->reserved_quantity);
        $seen = [];
        Event::listen(OrderPickupExpired::class, static function (OrderPickupExpired $event) use (&$seen): void {
            $seen[] = $event;
        });

        $this->assertSame(0, $this->fulfillment()->expireDue(new \DateTimeImmutable('+2 days'), 50));
        $this->assertSame('ready_for_pickup', $this->delivery($orderId)->fulfillment_state);

        $this->assertSame(1, $this->fulfillment()->expireDue(new \DateTimeImmutable('+4 days'), 50));
        $this->assertSame(0, $this->fulfillment()->expireDue(new \DateTimeImmutable('+5 days'), 50));

        $this->assertSame('pickup_expired', $this->delivery($orderId)->fulfillment_state);
        $this->assertSame('cancelled', DB::table('orders')->where('id', $orderId)->value('status'));
        $this->assertEquals(0, $this->stock()->reserved_quantity);
        $this->assertEquals(5, $this->stock()->on_hand_quantity);
        $this->assertDatabaseHas('order_delivery_events', ['order_id' => $orderId, 'type' => 'pickup_expired', 'actor_type' => 'system']);
        $this->assertDatabaseHas('order_outbox_events', ['order_id' => $orderId, 'event_type' => 'OrderPickupExpired']);
        $this->assertCount(1, $seen);
    }

    public function test_a_pay_on_pickup_order_without_a_deadline_never_lapses(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), null);

        $this->assertSame(0, $this->fulfillment()->expireDue(new \DateTimeImmutable('+400 days'), 50));
        $this->assertSame('ready_for_pickup', $this->delivery($orderId)->fulfillment_state);
    }

    public function test_a_prepaid_order_never_lapses_even_past_its_deadline(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(1));

        $this->assertSame(0, $this->fulfillment()->expireDue(new \DateTimeImmutable('+30 days'), 50));
        $this->assertFalse($this->fulfillment()->expireUncollected($orderId, new \DateTimeImmutable('+30 days')));
        $this->assertSame('ready_for_pickup', $this->delivery($orderId)->fulfillment_state);
        $this->assertSame('processing', DB::table('orders')->where('id', $orderId)->value('status'));
    }

    public function test_a_handed_over_order_cannot_lapse(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(1));
        $this->fulfillment()->confirmHandover($orderId, $this->actor(), 'KP-19');

        $this->assertSame(0, $this->fulfillment()->expireDue(new \DateTimeImmutable('+30 days'), 50));
        $this->assertSame('handed_over', $this->delivery($orderId)->fulfillment_state);
    }

    public function test_the_expiry_task_is_registered_with_the_scheduler(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(1));
        DB::table('order_deliveries')->where('order_id', $orderId)->update(['pickup_deadline_at' => now()->subMinute()]);

        $keys = array_map(static fn ($task): string => $task->key, app(ScheduledTaskRegistry::class)->all());
        $this->assertContains('core.orders.expire_uncollected', $keys);
        $this->assertContains('core.orders.remind_pickup', $keys);
    }

    private function emailCount(string $definition, string $orderId): int
    {
        return DB::table('email_messages')->where('definition_key', $definition)->where('idempotency_key', 'like', "order:{$orderId}:%")->count();
    }

    public function test_the_customer_gets_one_reminder_two_days_before_the_deadline(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(5));

        $this->assertSame(0, $this->fulfillment()->remindDue(new \DateTimeImmutable('+2 days'), 50), 'too early');
        $this->assertSame(1, $this->fulfillment()->remindDue(new \DateTimeImmutable('+3 days +1 minute'), 50));
        $this->assertSame(0, $this->fulfillment()->remindDue(new \DateTimeImmutable('+4 days'), 50), 'sent only once');

        $this->assertSame(1, $this->emailCount('core.order_pickup_reminder', $orderId));
        $this->assertDatabaseHas('order_delivery_events', ['order_id' => $orderId, 'type' => 'pickup_reminder_sent', 'actor_type' => 'system']);
        $this->assertNotNull($this->delivery($orderId)->pickup_reminder_sent_at);
    }

    public function test_no_reminder_when_the_deadline_is_too_close_or_absent_or_the_order_moved_on(): void
    {
        DB::table('stock_items')->where('variant_id', $this->variantId)->update(['on_hand_quantity' => 50]);
        $close = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($close, $this->actor(), $this->inDays(1));
        $none = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($none, $this->actor(), null);
        $handed = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($handed, $this->actor(), $this->inDays(5));
        $this->fulfillment()->confirmHandover($handed, $this->actor(), 'KP-20');

        $this->assertNull($this->delivery($close)->pickup_reminder_at, 'the ready e-mail already told the customer');
        $this->assertNull($this->delivery($none)->pickup_reminder_at);
        $this->assertSame(0, $this->fulfillment()->remindDue(new \DateTimeImmutable('+4 days'), 50));
        $this->assertSame(0, DB::table('email_messages')->where('definition_key', 'core.order_pickup_reminder')->count());
    }

    public function test_a_prepaid_order_is_reminded_too_without_the_cancellation_warning(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(5));
        $this->assertSame(1, $this->fulfillment()->remindDue(new \DateTimeImmutable('+4 days'), 50));

        $variables = EmailMessage::query()->where('definition_key', 'core.order_pickup_reminder')->firstOrFail()->variables;
        $this->assertSame('', $variables['lapses']);
    }

    public function test_an_expired_order_emails_the_customer_once(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(3));

        $this->fulfillment()->expireDue(new \DateTimeImmutable('+4 days'), 50);
        $this->fulfillment()->expireDue(new \DateTimeImmutable('+5 days'), 50);

        $this->assertSame(1, $this->emailCount('core.order_pickup_expired', $orderId));
        $this->assertNotSame('', EmailMessage::query()->where('definition_key', 'core.order_pickup_expired')->firstOrFail()->variables['pickup_deadline']);
    }

    public function test_the_guest_order_page_shows_the_pickup_state_to_the_customer(): void
    {
        $this->installReferenceTheme();
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $token = app(GuestOrderAccess::class)->issue($orderId);

        $this->get("/pl/orders/{$token}")->assertOk()->assertSee('data-pickup-state="pending"', false);

        $this->fulfillment()->markReadyForPickup($orderId, $this->actor(), $this->inDays(5));
        $this->get("/pl/orders/{$token}")->assertOk()
            ->assertSee('data-pickup-state="ready_for_pickup"', false)
            ->assertSee('Zamówienie jest gotowe do odbioru.')
            ->assertSee('Nieodebrane zamówienie zostanie anulowane.');

        $this->fulfillment()->confirmHandover($orderId, $this->actor(), 'KP-21');
        $this->get("/pl/orders/{$token}")->assertOk()
            ->assertSee('data-pickup-state="handed_over"', false)
            ->assertDontSee('Nieodebrane zamówienie zostanie anulowane.');
    }
}
