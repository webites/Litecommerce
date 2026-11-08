<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class PaymentNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sanitized_payload' => 'array', 'received_at' => 'datetime'];
    }
}
