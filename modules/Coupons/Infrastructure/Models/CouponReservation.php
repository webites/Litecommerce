<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $coupon_id
 * @property string $amount
 * @property string $state
 * @property string $reference_type
 * @property string $reference_id
 * @property Carbon|null $expires_at
 */
final class CouponReservation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'expires_at' => 'datetime',
        ];
    }
}
