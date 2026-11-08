<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $label
 * @property bool $active
 * @property int|null $max_downloads
 * @property int|null $validity_days
 * @property int $position
 * @property int|null $current_version_id
 */
final class VariantFileSet extends Model
{
    protected $fillable = ['variant_id', 'label', 'active', 'max_downloads', 'validity_days', 'position', 'current_version_id'];

    /** @return BelongsTo<DigitalAssetVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DigitalAssetVersion::class, 'current_version_id');
    }

    /** @return HasMany<DigitalAssetVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DigitalAssetVersion::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
