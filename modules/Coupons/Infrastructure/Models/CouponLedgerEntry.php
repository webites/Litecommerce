<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $coupon_id
 * @property string $type
 * @property string $amount
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $reason
 * @property string|null $actor_id
 * @property Carbon $occurred_at
 */
final class CouponLedgerEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6',
            'occurred_at' => 'datetime',
        ];
    }
}
