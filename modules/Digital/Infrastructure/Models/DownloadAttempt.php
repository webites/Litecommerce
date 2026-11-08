<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class DownloadAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'counted' => 'boolean'];
    }
}
