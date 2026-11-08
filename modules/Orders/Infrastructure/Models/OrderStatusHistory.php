<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
