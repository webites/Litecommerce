<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $active
 * @property int $position
 */
final class ReturnReason extends Model
{
    protected $fillable = ['key', 'name', 'active', 'position'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
