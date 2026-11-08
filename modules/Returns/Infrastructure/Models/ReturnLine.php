<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $return_request_id
 * @property int $order_line_id
 * @property string $quantity
 * @property int|null $reason_id
 * @property string|null $reason_comment
 * @property string $status
 * @property bool $restock
 * @property string|null $restocked_quantity
 * @property string|null $net_amount
 * @property string|null $vat_amount
 * @property string|null $gross_amount
 * @property list<array{code:string,amount:string,base_amount:string}>|null $coupon_allocations
 * @property string|null $money_refund_amount
 * @property int|null $refund_record_id
 * @property string|null $decision_reason
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 */
final class ReturnLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6', 'restocked_quantity' => 'decimal:6',
            'net_amount' => 'decimal:6', 'vat_amount' => 'decimal:6', 'gross_amount' => 'decimal:6',
            'money_refund_amount' => 'decimal:6', 'coupon_allocations' => 'array',
            'restock' => 'boolean', 'decided_at' => 'datetime',
        ];
    }
}
