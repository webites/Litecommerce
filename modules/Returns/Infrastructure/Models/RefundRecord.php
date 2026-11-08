<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_id
 * @property string $currency_code
 * @property string $products_net_amount
 * @property string $products_vat_amount
 * @property string $products_gross_amount
 * @property string $coupon_restored_amount
 * @property string|null $delivery_net_amount
 * @property string|null $delivery_vat_amount
 * @property string|null $delivery_gross_amount
 * @property string $money_refund_amount
 * @property string $decided_by
 * @property Carbon $decided_at
 * @property Carbon|null $paid_out_at
 * @property string|null $payout_reference
 * @property string|null $payout_recorded_by
 */
final class RefundRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'products_net_amount' => 'decimal:6', 'products_vat_amount' => 'decimal:6', 'products_gross_amount' => 'decimal:6',
            'coupon_restored_amount' => 'decimal:6', 'delivery_net_amount' => 'decimal:6', 'delivery_vat_amount' => 'decimal:6',
            'delivery_gross_amount' => 'decimal:6', 'money_refund_amount' => 'decimal:6',
            'decided_at' => 'datetime', 'paid_out_at' => 'datetime',
        ];
    }
}
