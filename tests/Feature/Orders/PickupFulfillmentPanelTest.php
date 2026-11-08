<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class PickupFulfillmentPanelTest extends TestCase
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
        DB::table('delivery_methods')->where('id', $this->pickupMethodId)->update(['supports_pay_on_pickup' => true, 'pickup_deadline_days' => 3]);
        $this->staff = StaffAccount::factory()->create();
        [, $this->variantId] = $this->createPhysicalVariant($this->staff, '10.00', 'gross', '5');
    }

    private function placeOrder(string $paymentMethod): string
    {
        $this->startNewShopper();
        [$response] = $this->checkoutVariant($this->variantId, '2', $paymentMethod);

        return (string) $response->assertOk()->json('order.id');
    }

    public function test_show_exposes_the_fulfillment_state_with_the_default_deadline_only_for_pay_on_pickup(): void
    {
        $prepaid = $this->placeOrder('core.bank_transfer');
        $payOnPickup = $this->placeOrder('core.pay_on_pickup');

        $this->actingAs($this->staff)->get(route('panel.orders.show', $prepaid))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.fulfillment.state', 'pending')
                ->where('order.fulfillment.can_mark_ready', false)->where('order.fulfillment.default_pickup_deadline_at', null));

        $this->actingAs($this->staff)->get(route('panel.orders.show', $payOnPickup))->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('order.fulfillment.can_mark_ready', true)
                ->where('order.fulfillment.requires_payment_on_handover', true)->whereNot('order.fulfillment.default_pickup_deadline_at', null));
    }

    public function test_staff_marks_ready_then_hands_over_and_both_are_audited(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);

        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.ready', $orderId), ['pickup_deadline_at' => now()->addDays(4)->format('Y-m-d\TH:i')])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'fulfillment_state' => 'ready_for_pickup']);

        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.handover', $orderId))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'fulfillment_state' => 'handed_over']);
        $this->assertDatabaseHas('audit_entries', ['action' => 'orders.fulfillment.ready_for_pickup', 'subject_id' => $orderId]);
        $this->assertDatabaseHas('audit_entries', ['action' => 'orders.fulfillment.handed_over', 'subject_id' => $orderId]);
    }

    public function test_repeating_an_action_is_not_audited_twice(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);

        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.ready', $orderId));
        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.ready', $orderId))->assertSessionHasNoErrors();

        $this->assertSame(1, DB::table('audit_entries')->where('action', 'orders.fulfillment.ready_for_pickup')->count());
    }

    public function test_a_domain_refusal_is_shown_as_a_form_error(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');

        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.ready', $orderId))
            ->assertSessionHasErrors('fulfillment');
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'fulfillment_state' => 'pending']);
    }

    public function test_unpaid_pay_on_pickup_handover_without_reference_is_refused(): void
    {
        $orderId = $this->placeOrder('core.pay_on_pickup');
        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.ready', $orderId))->assertSessionHasNoErrors();

        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.handover', $orderId))->assertSessionHasErrors('fulfillment');
        $this->actingAs($this->staff)->post(route('panel.orders.fulfillment.handover', $orderId), ['payment_reference' => 'CASH-1'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'fulfillment_state' => 'handed_over']);
    }

    public function test_actions_need_the_manage_orders_permission(): void
    {
        $orderId = $this->placeOrder('core.bank_transfer');
        $this->payOrder($orderId, $this->staff);
        $viewer = StaffAccount::factory()->create(['permission_overrides' => [CorePermission::ManageOrders->value => false]]);

        $this->actingAs($viewer)->post(route('panel.orders.fulfillment.ready', $orderId))->assertForbidden();
        $this->actingAs($viewer)->post(route('panel.orders.fulfillment.handover', $orderId))->assertForbidden();
        $this->assertDatabaseHas('order_deliveries', ['order_id' => $orderId, 'fulfillment_state' => 'pending']);
    }
}
