<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Application;

use Litecommerce\Modules\Returns\Contracts\ReturnCatalog;
use Litecommerce\Modules\Returns\Contracts\ReturnReasonData;
use Litecommerce\Modules\Returns\Contracts\ReturnRequestData;
use Litecommerce\Modules\Returns\Infrastructure\Models\RefundRecord;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnReason;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnRequest;

final class EloquentReturnCatalog implements ReturnCatalog
{
    public function findForOrder(string $orderId): array
    {
        $requests = ReturnRequest::query()->where('order_id', $orderId)->with('lines')->orderByDesc('created_at')->get();
        // Refunds aren't linked to a request directly (a decision batch can
        // span, in theory, only one request in this MVP flow) — fetch per
        // request via its lines' refund_record_id, cheaper than a join here
        // given the very small expected row counts per order.
        $refundIds = $requests->flatMap(fn (ReturnRequest $request) => $request->lines->pluck('refund_record_id'))->filter()->unique()->values();
        $refunds = RefundRecord::query()->whereIn('id', $refundIds)->get()->keyBy('id');

        return $requests->map(function (ReturnRequest $request) use ($refunds): ReturnRequestData {
            $requestRefundIds = $request->lines->pluck('refund_record_id')->filter()->unique();
            $requestRefunds = $refunds->only($requestRefundIds->all())->values();

            return ReturnDataMapper::toRequestData($request, $request->lines, $requestRefunds);
        })->values()->all();
    }

    public function find(string $returnRequestId): ?ReturnRequestData
    {
        $request = ReturnRequest::query()->with('lines')->find($returnRequestId);
        if ($request === null) {
            return null;
        }

        $refundIds = $request->lines->pluck('refund_record_id')->filter()->unique();
        $refunds = RefundRecord::query()->whereIn('id', $refundIds)->get();

        return ReturnDataMapper::toRequestData($request, $request->lines, $refunds);
    }

    public function activeReasons(): array
    {
        return ReturnReason::query()->where('active', true)->orderBy('position')->orderBy('id')->get()
            ->map(static fn (ReturnReason $reason): ReturnReasonData => ReturnDataMapper::toReasonData($reason))->values()->all();
    }

    public function allReasons(): array
    {
        return ReturnReason::query()->orderBy('position')->orderBy('id')->get()
            ->map(static fn (ReturnReason $reason): ReturnReasonData => ReturnDataMapper::toReasonData($reason))->values()->all();
    }
}
