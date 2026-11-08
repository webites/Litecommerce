<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Application;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Coupons\Contracts\CouponReservationManager;
use Litecommerce\Modules\Digital\Contracts\DigitalAccessManager;
use Litecommerce\Modules\Email\Contracts\EmailEnvelope;
use Litecommerce\Modules\Email\Contracts\TransactionalEmailQueue;
use Litecommerce\Modules\Orders\Contracts\GuestOrderAccess;
use Litecommerce\Modules\Orders\Contracts\OrderReturnableData;
use Litecommerce\Modules\Orders\Contracts\OrderReturnCatalog;
use Litecommerce\Modules\Returns\Contracts\RefundRecordData;
use Litecommerce\Modules\Returns\Contracts\ReturnOperationException;
use Litecommerce\Modules\Returns\Domain\Decimal;
use Litecommerce\Modules\Returns\Infrastructure\Models\RefundRecord;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnLine;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;
use Litecommerce\Modules\Store\Contracts\StoreSettingsReader;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;

/**
 * Approving/rejecting the lines of one ReturnRequest, per
 * docs/knowledge-base/04-pricing-coupons.md#snapshot-i-zwroty — money and
 * coupon amounts are a proportional share of the *recorded* order line
 * amounts, using largest-remainder rounding where the final return of a
 * line takes on its whole unaccounted remainder so cumulative returns
 * always reconstruct the original amount exactly.
 *
 * Rejecting a line never touches stock, digital access or money — only
 * an approved line does, atomically with recording the decision.
 */
final class DecideReturnLines
{
    public function __construct(
        private readonly OrderReturnCatalog $orders,
        private readonly StockAdjuster $stock,
        private readonly DigitalAccessManager $digitalAccess,
        private readonly CurrencyRepository $currencies,
        private readonly CouponReservationManager $coupons,
        private readonly TransactionalEmailQueue $emails,
        private readonly GuestOrderAccess $guestOrders,
        private readonly StoreSettingsReader $store,
    ) {}

    /** @param  list<array{line_id:int,decision:string,restock?:bool,restocked_quantity?:?string,decision_reason?:?string}>  $decisions */
    public function execute(string $returnRequestId, array $decisions, ?string $deliveryRefundGrossAmount, string $actorId): ?RefundRecordData
    {
        return DB::transaction(function () use ($returnRequestId, $decisions, $deliveryRefundGrossAmount, $actorId): ?RefundRecordData {
            $request = ReturnRequest::query()->lockForUpdate()->findOrFail($returnRequestId);
            $order = $this->orders->find($request->order_id);
            if ($order === null) {
                throw new ReturnOperationException('unknown_order', "Unknown order [{$request->order_id}].", ['order_id' => $request->order_id]);
            }
            $orderLinesById = collect($order->lines)->keyBy('id');
            $currency = $this->currencies->find($order->currencyCode);
            $decimals = $currency === null ? 2 : $currency->decimalPlaces;

            $approvedIds = [];
            $decisionCount = 0;
            $totals = ['net' => '0', 'vat' => '0', 'gross' => '0', 'coupon' => '0'];

            foreach ($decisions as $decision) {
                /** @var ReturnLine $line */
                $line = ReturnLine::query()->where('return_request_id', $returnRequestId)->lockForUpdate()->findOrFail($decision['line_id']);
                if ($line->status !== 'pending') {
                    continue;
                }

                if ($decision['decision'] === 'rejected') {
                    $line->update([
                        'status' => 'rejected', 'decision_reason' => $decision['decision_reason'] ?? null,
                        'decided_by' => $actorId, 'decided_at' => now(),
                    ]);
                    $decisionCount++;

                    continue;
                }

                $orderLine = $orderLinesById->get($line->order_line_id);
                if ($orderLine === null) {
                    throw new ReturnOperationException('order_line_not_resolvable', "Order line [{$line->order_line_id}] no longer resolvable.", ['line_id' => (string) $line->order_line_id]);
                }

                $alreadyApproved = $this->priorApprovedLines($orderLine->id, $line->id);
                $alreadyQuantity = $alreadyApproved->reduce(fn (string $c, ReturnLine $l): string => Decimal::add($c, (string) $l->quantity), '0');
                $remainingQuantity = Decimal::sub($orderLine->quantity, $alreadyQuantity);

                if (Decimal::compare($line->quantity, $remainingQuantity) > 0) {
                    throw new ReturnOperationException('approved_quantity_exceeds_ordered', "Approved quantity for line [{$line->order_line_id}] would exceed the ordered quantity.", ['line_id' => (string) $line->order_line_id]);
                }
                $isFinal = Decimal::compare($line->quantity, $remainingQuantity) === 0;

                $alreadyNet = $this->sumField($alreadyApproved, 'net_amount');
                $alreadyVat = $this->sumField($alreadyApproved, 'vat_amount');
                $alreadyGross = $this->sumField($alreadyApproved, 'gross_amount');

                $netAmount = $this->allocate($orderLine->netAmount, $line->quantity, $orderLine->quantity, $alreadyNet, $isFinal, $decimals);
                $vatAmount = $this->allocate($orderLine->vatAmount, $line->quantity, $orderLine->quantity, $alreadyVat, $isFinal, $decimals);
                $grossAmount = $this->allocate($orderLine->grossAmount, $line->quantity, $orderLine->quantity, $alreadyGross, $isFinal, $decimals);

                $couponAllocations = [];
                $couponTotal = '0';
                foreach ($orderLine->couponAllocations as $coupon) {
                    $alreadyCoupon = $this->sumCoupon($alreadyApproved, $coupon['code'], 'amount');
                    $amount = $this->allocate($coupon['amount'], $line->quantity, $orderLine->quantity, $alreadyCoupon, $isFinal, $decimals);

                    if (Decimal::compare($amount, '0') > 0) {
                        $alreadyBaseCoupon = $this->sumCoupon($alreadyApproved, $coupon['code'], 'base_amount');
                        $baseAmount = $this->allocate($coupon['base_amount'], $line->quantity, $orderLine->quantity, $alreadyBaseCoupon, $isFinal, 6);

                        $couponAllocations[] = ['code' => $coupon['code'], 'amount' => $amount, 'base_amount' => $baseAmount];
                        $couponTotal = Decimal::add($couponTotal, $amount);

                        if (Decimal::compare($baseAmount, '0') > 0) {
                            $this->coupons->restore($coupon['code'], $baseAmount, 'return_line', (string) $line->id, trans('returns::panel.reasons.return_approved'));
                        }
                    }
                }

                $moneyRefund = Decimal::sub($grossAmount, $couponTotal);
                $isDigital = $orderLine->productType === ProductType::Digital->value;
                $restock = ! $isDigital && (bool) ($decision['restock'] ?? false);
                $restockedQuantity = $restock ? ($decision['restocked_quantity'] ?? $line->quantity) : null;
                if ($restock && Decimal::compare($restockedQuantity, $line->quantity) > 0) {
                    throw new ReturnOperationException('restocked_quantity_exceeds_returned', "Restocked quantity for line [{$line->order_line_id}] cannot exceed the returned quantity.", ['line_id' => (string) $line->order_line_id]);
                }

                $line->update([
                    'status' => 'approved', 'restock' => $restock, 'restocked_quantity' => $restockedQuantity,
                    'net_amount' => $netAmount, 'vat_amount' => $vatAmount, 'gross_amount' => $grossAmount,
                    'coupon_allocations' => $couponAllocations, 'money_refund_amount' => $moneyRefund,
                    'decided_by' => $actorId, 'decided_at' => now(),
                ]);

                if ($restock) {
                    $this->stock->returnToStock((int) $orderLine->variantId, (string) $restockedQuantity, trans('returns::panel.reasons.return_approved_line', ['line_id' => $line->id]), $actorId);
                }
                if ($isDigital) {
                    $this->digitalAccess->revokeForReference('order_line', (string) $orderLine->id, trans('returns::panel.reasons.return_confirmed'));
                }

                $approvedIds[] = $line->id;
                $decisionCount++;
                $totals['net'] = Decimal::add($totals['net'], $netAmount);
                $totals['vat'] = Decimal::add($totals['vat'], $vatAmount);
                $totals['gross'] = Decimal::add($totals['gross'], $grossAmount);
                $totals['coupon'] = Decimal::add($totals['coupon'], $couponTotal);
            }

            $refundData = null;
            if ($approvedIds !== []) {
                [$deliveryNet, $deliveryVat, $deliveryGross] = $this->deliveryRefund($order, $deliveryRefundGrossAmount, $decimals);
                $moneyRefundTotal = Decimal::sub($totals['gross'], $totals['coupon']);
                if ($deliveryGross !== null) {
                    $moneyRefundTotal = Decimal::add($moneyRefundTotal, $deliveryGross);
                }

                $refund = RefundRecord::query()->create([
                    'order_id' => $order->id, 'currency_code' => $order->currencyCode,
                    'products_net_amount' => $totals['net'], 'products_vat_amount' => $totals['vat'],
                    'products_gross_amount' => $totals['gross'], 'coupon_restored_amount' => $totals['coupon'],
                    'delivery_net_amount' => $deliveryNet, 'delivery_vat_amount' => $deliveryVat, 'delivery_gross_amount' => $deliveryGross,
                    'money_refund_amount' => $moneyRefundTotal, 'decided_by' => $actorId, 'decided_at' => now(),
                ]);
                ReturnLine::query()->whereIn('id', $approvedIds)->update(['refund_record_id' => $refund->getKey()]);
                $refundData = ReturnDataMapper::toRefundData($refund);
            }

            if (! ReturnLine::query()->where('return_request_id', $returnRequestId)->where('status', 'pending')->exists()) {
                $request->update(['status' => 'closed']);
            }

            if ($decisionCount > 0) {
                $decidedTotal = ReturnLine::query()->where('return_request_id', $returnRequestId)->whereIn('status', ['approved', 'rejected'])->count();
                $approvedTotal = ReturnLine::query()->where('return_request_id', $returnRequestId)->where('status', 'approved')->count();
                $rejectedTotal = ReturnLine::query()->where('return_request_id', $returnRequestId)->where('status', 'rejected')->count();
                $url = $order->customerAccountId === null
                    ? route('storefront.orders.guest.show', ['locale' => $order->locale, 'token' => $this->guestOrders->issue($order->id)])
                    : route('storefront.account.orders.show', ['locale' => $order->locale, 'order' => $order->id]);
                $this->emails->enqueue(new EmailEnvelope(
                    'core.return_decided',
                    $order->email,
                    null,
                    $order->locale,
                    [
                        'store_name' => $this->store->current()->storeName,
                        'order_id' => $order->id,
                        'decision' => trans('returns::panel.emails.return_decided.decision', [], $order->locale),
                        'details' => trans('returns::panel.emails.return_decided.details', ['approved' => $approvedTotal, 'rejected' => $rejectedTotal], $order->locale),
                        'order_url' => $url,
                    ],
                    "return:{$returnRequestId}:decisions:{$decidedTotal}",
                    'return_request',
                    $returnRequestId,
                    $order->mode,
                    actorType: 'staff',
                    actorId: $actorId,
                ));
            }

            return $refundData;
        });
    }

    /** @return Collection<int, ReturnLine> */
    private function priorApprovedLines(int $orderLineId, int $excludingLineId)
    {
        return ReturnLine::query()->where('order_line_id', $orderLineId)->where('status', 'approved')
            ->where('id', '!=', $excludingLineId)->get();
    }

    /** @param  Collection<int, ReturnLine>  $lines */
    private function sumField($lines, string $field): string
    {
        return $lines->reduce(fn (string $carry, ReturnLine $line): string => Decimal::add($carry, (string) ($line->{$field} ?? '0')), '0');
    }

    /** @param  Collection<int, ReturnLine>  $lines */
    private function sumCoupon($lines, string $code, string $field): string
    {
        return $lines->reduce(function (string $carry, ReturnLine $line) use ($code, $field): string {
            foreach ($line->coupon_allocations ?? [] as $allocation) {
                if ($allocation['code'] === $code) {
                    return Decimal::add($carry, $allocation[$field] ?? $allocation['amount']);
                }
            }

            return $carry;
        }, '0');
    }

    private function allocate(string $original, string $lineQuantity, string $totalQuantity, string $alreadyAllocated, bool $isFinal, int $decimals): string
    {
        if ($isFinal) {
            return Decimal::roundHalfUp(Decimal::sub($original, $alreadyAllocated), $decimals);
        }

        return Decimal::roundHalfUp(Decimal::mul($original, Decimal::div($lineQuantity, $totalQuantity)), $decimals);
    }

    /** @return array{0:?string,1:?string,2:?string} */
    private function deliveryRefund(OrderReturnableData $order, ?string $requestedGross, int $decimals): array
    {
        if ($requestedGross === null || Decimal::compare($requestedGross, '0') <= 0) {
            return [null, null, null];
        }
        if ($order->deliveryGrossAmount === null) {
            throw new ReturnOperationException('no_delivery_to_refund', 'This order has no delivery line to refund.');
        }

        $alreadyRefunded = RefundRecord::query()->where('order_id', $order->id)->whereNotNull('delivery_gross_amount')
            ->get()->reduce(fn (string $c, RefundRecord $r): string => Decimal::add($c, (string) $r->delivery_gross_amount), '0');
        $remaining = Decimal::sub($order->deliveryGrossAmount, $alreadyRefunded);
        if (Decimal::compare($requestedGross, $remaining) > 0) {
            throw new ReturnOperationException('delivery_refund_exceeds_remaining', "Delivery refund amount exceeds the remaining refundable value ({$remaining}).", ['remaining' => $remaining]);
        }

        // Delivery has no quantity to prorate against — split net/VAT using
        // the original delivery's net:gross ratio, applied to the amount
        // staff chose to refund.
        $ratio = Decimal::div($order->deliveryNetAmount ?? '0', $order->deliveryGrossAmount);
        $net = Decimal::roundHalfUp(Decimal::mul($requestedGross, $ratio), $decimals);
        $vat = Decimal::roundHalfUp(Decimal::sub($requestedGross, $net), $decimals);

        return [$net, $vat, $requestedGross];
    }
}
