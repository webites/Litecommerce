<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cart\Contracts\AdjustmentContext;
use Litecommerce\Modules\Cart\Contracts\AdjustmentData;
use Litecommerce\Modules\Cart\Contracts\PricingAdjustment;
use Litecommerce\Modules\Cart\Contracts\PricingAdjustmentRegistry;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Litecommerce\Modules\Returns\Application\DecideReturnLines;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

final class PricingAdjustmentTest extends TestCase
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
        $this->staff = StaffAccount::factory()->create();
        $taxRateId = (int) DB::table('tax_rates')->insertGetId(['name' => 'Podstawowa', 'kind' => 'percentage', 'percentage' => '23.00', 'created_at' => now(), 'updated_at' => now()]);
        [, $this->variantId] = $this->createPhysicalVariant($this->staff, '10.00', 'net', '10', $taxRateId);
    }

    private function registerFee(string $net = '8.13', string $vat = '1.87', string $gross = '10.00', string $code = 'test.handling'): void
    {
        app(PricingAdjustmentRegistry::class)->register(new class($net, $vat, $gross, $code) implements PricingAdjustment
        {
            public function __construct(private string $net, private string $vat, private string $gross, private string $code) {}

            public function adjust(AdjustmentContext $context): array
            {
                return [new AdjustmentData($this->code, 'Handling fee', $this->net, $this->vat, $this->gross)];
            }
        });
    }

    private function startDraft(string $quantity): string
    {
        $cart = $this->postJson('/cart/items', ['variant_id' => $this->variantId, 'quantity' => $quantity])->assertOk();
        $this->carryCookiesFrom($cart);
        $draftId = (string) $this->postJson('/checkout')->assertCreated()->json('id');
        $this->putJson("/checkout/{$draftId}", [
            'buyer_type' => 'person', 'email' => 'buyer@example.com',
            'billing_address' => ['recipient_name' => 'Jan Kowalski', 'country_code' => 'PL', 'postal_code' => '00-001', 'city' => 'Warszawa', 'address_line_1' => 'Testowa 1'],
            'ships_to_different_address' => false,
            'delivery_method_id' => 'core.'.$this->pickupMethodId, 'delivery_pickup_point_id' => $this->pickupPointId,
        ])->assertOk();

        return $draftId;
    }

    public function test_an_adjustment_changes_the_price_hash_and_the_grand_total(): void
    {
        $draftId = $this->startDraft('1');
        $before = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');

        $this->registerFee();
        $after = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');

        $this->assertNotSame($before['hash'], $after['hash']);
        $this->assertSame('Handling fee', $after['adjustments'][0]['label']);
        $this->assertSame(bcadd($before['grand_total_gross_amount'], '10.00', 2), $after['grand_total_gross_amount']);
        $this->assertSame(bcadd($before['grand_total_net_amount'], '8.13', 2), $after['grand_total_net_amount']);
    }

    public function test_the_order_total_includes_the_adjustment_and_each_line_keeps_its_share(): void
    {
        $this->registerFee();
        $draftId = $this->startDraft('3');
        $price = $this->getJson("/checkout/{$draftId}")->assertOk()->json('price');

        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $price['hash'], 'payment_method_id' => 'core.bank_transfer'])
            ->assertOk()->json('order.id');

        $this->assertSame($price['grand_total_gross_amount'], bcadd((string) DB::table('orders')->where('id', $orderId)->value('total_gross_amount'), '0', 2));
        $snapshot = json_decode((string) DB::table('order_lines')->where('order_id', $orderId)->value('allocations_snapshot'), true);
        $this->assertSame('10.00', bcadd($snapshot['adjustments'][0]['gross_amount'], '0', 2));
        $this->assertSame('1.87', $snapshot['adjustments'][0]['vat_amount']);
    }

    public function test_the_customer_confirmation_page_lists_the_adjustment(): void
    {
        $this->installReferenceTheme();
        DB::table('languages')->where('code', 'pl')->update(['is_enabled' => true]);
        $this->registerFee();
        $draftId = $this->startDraft('1');
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk();

        $this->get("/pl/checkout/{$draftId}/thank-you")->assertOk()->assertSee('Handling fee');
    }

    public function test_a_stale_hash_is_rejected_when_an_adjustment_appears_before_confirmation(): void
    {
        $draftId = $this->startDraft('1');
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');

        $this->registerFee();

        $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])
            ->assertStatus(409)->assertJsonPath('code', 'price_changed');
    }

    public function test_partial_returns_refund_the_adjustment_in_proportion_and_add_up_exactly(): void
    {
        $this->registerFee();
        $draftId = $this->startDraft('3');
        $hash = (string) $this->getJson("/checkout/{$draftId}")->assertOk()->json('price.hash');
        $orderId = (string) $this->postJson("/checkout/{$draftId}/confirm", ['price_hash' => $hash, 'payment_method_id' => 'core.bank_transfer'])->assertOk()->json('order.id');
        $lineId = (int) DB::table('order_lines')->where('order_id', $orderId)->value('id');
        $this->payOrder($orderId, $this->staff);

        $refunds = [];
        foreach (['1', '2'] as $quantity) {
            $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
                ['order_line_id' => $lineId, 'quantity' => $quantity, 'reason_id' => null, 'reason_comment' => null],
            ]);
            $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
            $refunds[] = app(DecideReturnLines::class)->execute($request->id, [['line_id' => $returnLineId, 'decision' => 'approved']], null, (string) $this->staff->getKey());
        }

        $this->assertSame('3.330000', $refunds[0]->adjustmentsGrossAmount);
        $this->assertSame('10.000000', bcadd($refunds[0]->adjustmentsGrossAmount, $refunds[1]->adjustmentsGrossAmount, 6));
        $this->assertSame('1.870000', bcadd($refunds[0]->adjustmentsVatAmount, $refunds[1]->adjustmentsVatAmount, 6));
        $this->assertSame('8.130000', bcadd($refunds[0]->adjustmentsNetAmount, $refunds[1]->adjustmentsNetAmount, 6));
        $this->assertSame(bcadd($refunds[0]->productsGrossAmount, $refunds[0]->adjustmentsGrossAmount, 6), $refunds[0]->moneyRefundAmount);
    }

    public function test_an_adjustment_whose_net_and_vat_do_not_add_up_stops_the_checkout(): void
    {
        $this->registerFee(net: '8.00', vat: '1.87', gross: '10.00');
        $draftId = $this->startDraft('1');

        $this->withoutExceptionHandling();
        $this->expectException(\InvalidArgumentException::class);
        $this->getJson("/checkout/{$draftId}");
    }

    public function test_a_discount_larger_than_the_order_stops_the_checkout(): void
    {
        $this->registerFee(net: '-1000.00', vat: '0.00', gross: '-1000.00');
        $draftId = $this->startDraft('1');

        $this->withoutExceptionHandling();
        $this->expectException(\LogicException::class);
        $this->getJson("/checkout/{$draftId}");
    }
}
