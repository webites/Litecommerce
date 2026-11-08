<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property int|null $parent_id @property int $position @property bool $visible_in_tree */
final class Category extends Model
{
    protected $fillable = ['parent_id', 'position', 'visible_in_tree', 'image_media_id'];

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<CategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer', 'visible_in_tree' => 'boolean'];
    }
}
