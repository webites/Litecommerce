<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $location_id
 * @property array<int, array<string, mixed>> $content
 */
final class Menu extends Model
{
    protected $fillable = ['name', 'location_id', 'content'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
