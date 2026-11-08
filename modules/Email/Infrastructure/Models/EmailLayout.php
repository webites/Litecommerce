<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class EmailLayout extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
