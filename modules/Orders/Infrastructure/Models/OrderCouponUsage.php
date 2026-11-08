<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $order_id
 * @property string $coupon_code
 * @property int|null $coupon_reservation_id
 * @property string $product_gross_amount
 * @property string $delivery_gross_amount
 */
final class OrderCouponUsage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'product_gross_amount' => 'decimal:6',
            'delivery_gross_amount' => 'decimal:6',
        ];
    }
}
