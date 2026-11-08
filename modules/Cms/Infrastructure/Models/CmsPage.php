<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property Carbon|null $publish_at
 * @property Carbon|null $archived_at
 * @property bool $is_homepage
 * @property string|null $archetype
 */
final class CmsPage extends Model
{
    protected $fillable = ['status', 'publish_at', 'archived_at', 'is_homepage', 'archetype'];

    /** @return HasMany<CmsPageTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CmsPageTranslation::class, 'page_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_homepage' => 'boolean',
            'publish_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
