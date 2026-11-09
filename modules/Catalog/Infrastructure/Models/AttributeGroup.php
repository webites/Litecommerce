<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $key @property int $position */
final class AttributeGroup extends Model
{
    protected $fillable = ['key', 'position'];

    /** @return HasMany<AttributeGroupTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeGroupTranslation::class);
    }

    /** @return HasMany<AttributeDefinition, $this> */
    public function definitions(): HasMany
    {
        return $this->hasMany(AttributeDefinition::class);
    }
}
