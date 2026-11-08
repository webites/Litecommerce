<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_delivery_id
 * @property string $order_id
 * @property string $type
 * @property Carbon $occurred_at
 * @property string $actor_type
 * @property string|null $actor_id
 * @property array<string, mixed>|null $data
 */
final class OrderDeliveryEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'data' => 'array'];
    }
}
