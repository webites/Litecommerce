<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Litecommerce\Modules\Orders\Contracts\OrderReturnCatalog;
use Litecommerce\Modules\Returns\Contracts\ReturnOperationException;
use Litecommerce\Modules\Returns\Domain\Decimal;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnLine;
use Litecommerce\Modules\Returns\Infrastructure\Models\ReturnRequest;

/**
 * "Częściowe anulowanie niezapłaconej pozycji nie może tworzyć
 * fikcyjnego zwrotu pieniędzy" (docs/knowledge-base/11-open-decisions.md,
 * rozstrzygnięte w docs/knowledge-base/06-fulfillment.md#projekt-techniczny-p1-010) —
 * a return request only exists for money that was actually collected.
 * An order that never reached a paid state goes through
 * Orders\Contracts\OrderPaymentManager::cancelUnpaid() instead.
 */
final class CreateReturnRequest
{
    public function __construct(private readonly OrderReturnCatalog $orders) {}

    /** @param  list<array{order_line_id:int,quantity:string,reason_id:?int,reason_comment:?string}>  $lines */
    public function execute(string $orderId, string $source, ?int $customerAccountId, string $email, ?string $comment, array $lines): ReturnRequest
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new ReturnOperationException('unknown_order', "Unknown order [{$orderId}].", ['order_id' => $orderId]);
        }
        if ($order->paidAt === null) {
            throw new ReturnOperationException('order_not_paid', 'Order has not been paid yet — there is nothing to return. Use order cancellation instead.');
        }
        if ($lines === []) {
            throw new ReturnOperationException('no_lines', 'The return request must contain at least one line.');
        }

        return DB::transaction(function () use ($order, $orderId, $source, $customerAccountId, $email, $comment, $lines): ReturnRequest {
            $request = ReturnRequest::query()->create([
                'id' => (string) Str::uuid(), 'order_id' => $orderId, 'source' => $source,
                'customer_account_id' => $customerAccountId, 'email' => $email, 'comment' => $comment, 'status' => 'open',
            ]);

            $orderLinesById = collect($order->lines)->keyBy('id');

            foreach ($lines as $line) {
                $orderLine = $orderLinesById->get($line['order_line_id']);
                if ($orderLine === null) {
                    throw new ReturnOperationException('line_not_in_order', "Order line [{$line['order_line_id']}] does not belong to this order.", ['line_id' => (string) $line['order_line_id']]);
                }

                $alreadyApproved = ReturnLine::query()->where('order_line_id', $orderLine->id)->where('status', 'approved')
                    ->get()->reduce(fn (string $carry, ReturnLine $l): string => Decimal::add($carry, (string) $l->quantity), '0');
                $remaining = Decimal::sub($orderLine->quantity, $alreadyApproved);

                if (Decimal::compare($line['quantity'], '0') <= 0) {
                    throw new ReturnOperationException('quantity_must_be_positive', "Return quantity for line [{$line['order_line_id']}] must be positive.", ['line_id' => (string) $line['order_line_id']]);
                }
                if (Decimal::compare($line['quantity'], $remaining) > 0) {
                    throw new ReturnOperationException('quantity_exceeds_remaining', "Return quantity for line [{$line['order_line_id']}] exceeds the remaining returnable quantity ({$remaining}).", ['line_id' => (string) $line['order_line_id'], 'remaining' => $remaining]);
                }

                ReturnLine::query()->create([
                    'return_request_id' => $request->id, 'order_line_id' => $orderLine->id,
                    'quantity' => $line['quantity'], 'reason_id' => $line['reason_id'] ?? null,
                    'reason_comment' => $line['reason_comment'] ?? null, 'status' => 'pending',
                ]);
            }

            return $request;
        });
    }
}
