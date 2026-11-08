<?php

declare(strict_types=1);

namespace Tests\Feature\Returns;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Identity\Infrastructure\Models\StaffAccount;
use Litecommerce\Modules\Installer\Infrastructure\InstallationLock;
use Litecommerce\Modules\Returns\Application\CreateReturnRequest;
use Litecommerce\Modules\Returns\Application\DecideReturnLines;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PlacesStorefrontOrders;
use Tests\Concerns\UsesIsolatedStorage;
use Tests\TestCase;

/**
 * Whatever way a line is split into partial returns, the refunds must add up to the recorded line amounts to the last grosz.
 */
final class RefundReconstructionPropertyTest extends TestCase
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

    /** @return iterable<string, array{0: string, 1: string, 2: list<int>, 3: string}> */
    public static function splits(): iterable
    {
        yield 'seven singles, 23% on 3.33 net' => ['3.33', '23.00', [1, 1, 1, 1, 1, 1, 1], 'net'];
        yield 'two chunks, 8% on 9.99 net' => ['9.99', '8.00', [2, 5], 'net'];
        yield 'uneven chunks, 23% on 12.30 gross' => ['12.30', '23.00', [3, 3, 1], 'gross'];
        yield 'one then the rest, 5% on 0.97 net' => ['0.97', '5.00', [1, 6], 'net'];
        yield 'rest then singles, 23% on 7.77 gross' => ['7.77', '23.00', [4, 2, 1], 'gross'];
    }

    /** @param  list<int>  $chunks */
    #[DataProvider('splits')]
    public function test_partial_refunds_rebuild_the_line_amounts_exactly(string $price, string $vat, array $chunks, string $mode): void
    {
        $staff = StaffAccount::factory()->create();
        $taxRateId = (int) DB::table('tax_rates')->insertGetId(['name' => 'Stawka', 'kind' => 'percentage', 'percentage' => $vat, 'created_at' => now(), 'updated_at' => now()]);
        [, $variantId] = $this->createPhysicalVariant($staff, $price, $mode, stock: '20', taxRateId: $taxRateId);
        $this->startNewShopper();
        [$response] = $this->checkoutVariant($variantId, (string) array_sum($chunks));
        $orderId = (string) $response->assertOk()->json('order.id');
        $this->payOrder($orderId, $staff);
        $line = DB::table('order_lines')->where('order_id', $orderId)->first();

        $net = '0';
        $taxAmount = '0';
        $gross = '0';
        foreach ($chunks as $quantity) {
            $request = app(CreateReturnRequest::class)->execute($orderId, 'staff', null, 'buyer@example.com', null, [
                ['order_line_id' => $line->id, 'quantity' => (string) $quantity, 'reason_id' => null, 'reason_comment' => null],
            ]);
            $returnLineId = (int) DB::table('return_lines')->where('return_request_id', $request->id)->value('id');
            $refund = app(DecideReturnLines::class)->execute($request->id, [['line_id' => $returnLineId, 'decision' => 'approved']], null, (string) $staff->getKey());

            $this->assertNotNull($refund);
            $net = bcadd($net, $refund->productsNetAmount, 6);
            $taxAmount = bcadd($taxAmount, $refund->productsVatAmount, 6);
            $gross = bcadd($gross, $refund->productsGrossAmount, 6);
            $this->assertSame(bcadd($refund->productsNetAmount, $refund->productsVatAmount, 6), $refund->productsGrossAmount, 'each refund keeps net + VAT = gross');
        }

        $this->assertSame(bcadd((string) $line->line_net_amount, '0', 6), $net);
        $this->assertSame(bcadd((string) $line->line_vat_amount, '0', 6), $taxAmount);
        $this->assertSame(bcadd((string) $line->line_gross_amount, '0', 6), $gross);
    }
}
