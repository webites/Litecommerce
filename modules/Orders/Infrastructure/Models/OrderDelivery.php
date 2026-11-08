<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_id
 * @property string|null $delivery_method_id
 * @property string|null $kind
 * @property string|null $name
 * @property string|null $net_amount
 * @property string|null $vat_amount
 * @property string|null $gross_amount
 * @property int|null $pickup_point_id
 * @property array<string, mixed>|null $pickup_point_snapshot
 * @property string $fulfillment_state
 * @property Carbon|null $ready_for_pickup_at
 * @property Carbon|null $pickup_deadline_at
 * @property Carbon|null $handed_over_at
 * @property Carbon|null $pickup_reminder_at
 * @property Carbon|null $pickup_reminder_sent_at
 */
final class OrderDelivery extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<OrderDeliveryEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OrderDeliveryEvent::class);
    }

    protected function casts(): array
    {
        return [
            'pickup_point_snapshot' => 'array',
            'ready_for_pickup_at' => 'datetime', 'pickup_deadline_at' => 'datetime', 'handed_over_at' => 'datetime',
            'pickup_reminder_at' => 'datetime', 'pickup_reminder_sent_at' => 'datetime',
        ];
    }
}
