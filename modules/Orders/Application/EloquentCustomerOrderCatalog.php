<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Contracts\CustomerOrderCatalog;
use Litecommerce\Modules\Orders\Contracts\CustomerOrderSummaryData;
use Litecommerce\Modules\Orders\Contracts\OrderDetailData;
use Litecommerce\Modules\Orders\Infrastructure\Models\Order;

final class EloquentCustomerOrderCatalog implements CustomerOrderCatalog
{
    public function __construct(private readonly OrderProjector $projector) {}

    public function paginateForCustomer(int $customerAccountId, int $perPage, int $page): array
    {
        $query = Order::query()->where('customer_account_id', $customerAccountId)
            ->withCount('lines')->orderByDesc('created_at');

        $total = (clone $query)->toBase()->getCountForPagination();
        $orders = $query->forPage($page, $perPage)->get();

        return [
            'items' => $orders->map(static fn (Order $order): CustomerOrderSummaryData => new CustomerOrderSummaryData(
                $order->id, $order->public_number, $order->status, $order->payment_status,
                (string) $order->total_gross_amount, $order->currency_code,
                $order->created_at === null ? null : \DateTimeImmutable::createFromInterface($order->created_at),
                (int) $order->lines_count,
            ))->values()->all(),
            'total' => $total,
        ];
    }

    public function findForCustomer(string $orderId, int $customerAccountId): ?OrderDetailData
    {
        $order = Order::query()->where('customer_account_id', $customerAccountId)
            ->with(['party', 'delivery', 'lines'])->find($orderId);

        return $order === null ? null : $this->projector->detail($order);
    }
}
