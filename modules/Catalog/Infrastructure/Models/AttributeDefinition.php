<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $key @property string $type @property ?int $attribute_group_id @property int $position @property bool $is_filterable @property bool $visible_on_product */
final class AttributeDefinition extends Model
{
    protected $fillable = ['key', 'type', 'attribute_group_id', 'position', 'is_filterable', 'visible_on_product'];

    /** @return HasMany<AttributeDefinitionTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeDefinitionTranslation::class);
    }

    /** @return BelongsTo<AttributeGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(AttributeGroup::class, 'attribute_group_id');
    }

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_filterable' => 'boolean', 'visible_on_product' => 'boolean'];
    }
}
