<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $version
 * @property string|null $package_sha256
 * @property string $type
 * @property string $status
 * @property array<string, mixed> $manifest
 * @property Carbon|null $installed_at
 * @property Carbon|null $enabled_at
 */
final class Extension extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'installed_at' => 'datetime',
            'enabled_at' => 'datetime',
        ];
    }
}
