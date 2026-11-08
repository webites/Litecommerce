<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class OrderOutboxEvent extends Model
{
    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime', 'available_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
