<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Application;

use Illuminate\Support\Collection;
use Litecommerce\Modules\Returns\Contracts\RefundRecordData;
use Litecommerce\Modules\Returns\Contracts\ReturnLineData;
use Litecommerce\Modules\Returns\Contracts\ReturnReasonData;
use Litecommerce\Modules\Returns\Contracts\ReturnRequestData;
use Litecommerce\Modules\Returns\Infrastructure\Models\RefundRecord;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnLine;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnReason;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnRequest;

final class ReturnDataMapper
{
    public static function toReasonData(ReturnReason $reason): ReturnReasonData
    {
        return new ReturnReasonData((int) $reason->getKey(), $reason->key, $reason->name, $reason->active);
    }

    public static function toLineData(ReturnLine $line): ReturnLineData
    {
        return new ReturnLineData(
            id: (int) $line->getKey(), orderLineId: $line->order_line_id, quantity: $line->quantity,
            reasonId: $line->reason_id, reasonComment: $line->reason_comment, status: $line->status,
            restock: $line->restock, restockedQuantity: $line->restocked_quantity,
            netAmount: $line->net_amount, vatAmount: $line->vat_amount, grossAmount: $line->gross_amount,
            couponAllocations: $line->coupon_allocations ?? [], moneyRefundAmount: $line->money_refund_amount,
            decisionReason: $line->decision_reason, decidedBy: $line->decided_by,
            decidedAt: $line->decided_at === null ? null : \DateTimeImmutable::createFromInterface($line->decided_at),
            adjustmentAllocations: $line->adjustment_allocations ?? [],
        );
    }

    public static function toRefundData(RefundRecord $refund): RefundRecordData
    {
        return new RefundRecordData(
            id: (int) $refund->getKey(), orderId: $refund->order_id, currencyCode: $refund->currency_code,
            productsNetAmount: $refund->products_net_amount, productsVatAmount: $refund->products_vat_amount,
            productsGrossAmount: $refund->products_gross_amount, couponRestoredAmount: $refund->coupon_restored_amount,
            deliveryNetAmount: $refund->delivery_net_amount, deliveryVatAmount: $refund->delivery_vat_amount,
            deliveryGrossAmount: $refund->delivery_gross_amount, moneyRefundAmount: $refund->money_refund_amount,
            decidedBy: $refund->decided_by, decidedAt: \DateTimeImmutable::createFromInterface($refund->decided_at),
            paidOutAt: $refund->paid_out_at === null ? null : \DateTimeImmutable::createFromInterface($refund->paid_out_at),
            payoutReference: $refund->payout_reference,
            adjustmentsNetAmount: $refund->adjustments_net_amount, adjustmentsVatAmount: $refund->adjustments_vat_amount,
            adjustmentsGrossAmount: $refund->adjustments_gross_amount,
        );
    }

    /**
     * @param  Collection<int, ReturnLine>  $lines
     * @param  Collection<int, RefundRecord>  $refunds
     */
    public static function toRequestData(ReturnRequest $request, $lines, $refunds): ReturnRequestData
    {
        return new ReturnRequestData(
            id: $request->id, orderId: $request->order_id, source: $request->source,
            customerAccountId: $request->customer_account_id, email: $request->email, comment: $request->comment,
            status: $request->status, createdAt: \DateTimeImmutable::createFromInterface($request->created_at),
            lines: $lines->map(static fn (ReturnLine $line): ReturnLineData => self::toLineData($line))->values()->all(),
            refunds: $refunds->map(static fn (RefundRecord $refund): RefundRecordData => self::toRefundData($refund))->values()->all(),
        );
    }
}
