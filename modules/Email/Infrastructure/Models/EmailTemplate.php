<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class EmailTemplate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
