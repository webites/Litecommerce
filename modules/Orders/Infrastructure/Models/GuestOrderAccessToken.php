<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class GuestOrderAccessToken extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
