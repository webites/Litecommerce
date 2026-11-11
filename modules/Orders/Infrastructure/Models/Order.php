<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $number
 * @property string $public_number
 * @property int|null $customer_account_id
 * @property string $status
 * @property string $payment_status
 * @property string $payment_method_id
 * @property string $payment_kind
 * @property string $currency_code
 * @property string $mode
 * @property string $locale
 * @property string $source_type
 * @property string $source_id
 * @property string $email
 * @property string $total_net_amount
 * @property string $total_vat_amount
 * @property string $total_gross_amount
 * @property string|null $paid_attempt_id
 * @property int $version
 * @property bool $deadline_extended
 * @property Carbon|null $payment_deadline_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $anonymized_at
 */
final class Order extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return HasOne<OrderPartySnapshot, $this> */
    public function party(): HasOne
    {
        return $this->hasOne(OrderPartySnapshot::class);
    }

    /** @return HasOne<OrderDelivery, $this> */
    public function delivery(): HasOne
    {
        return $this->hasOne(OrderDelivery::class);
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return HasMany<OrderNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class);
    }

    /** @return HasMany<OrderCouponUsage, $this> */
    public function couponUsages(): HasMany
    {
        return $this->hasMany(OrderCouponUsage::class);
    }

    protected function casts(): array
    {
        return ['payment_deadline_at' => 'datetime', 'paid_at' => 'datetime', 'anonymized_at' => 'datetime', 'deadline_extended' => 'boolean'];
    }
}
