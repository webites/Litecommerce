<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_default
 * @property bool $is_enabled
 * @property bool $noindex
 */
final class Language extends Model
{
    protected $table = 'languages';

    protected $fillable = ['code', 'name', 'is_default', 'is_enabled', 'noindex'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_enabled' => 'boolean',
        'noindex' => 'boolean',
    ];

    // Route-model-binds by the immutable, human-readable code (/panel/languages/pl)
    // rather than the numeric id — same reasoning as any other natural-key lookup.
    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
