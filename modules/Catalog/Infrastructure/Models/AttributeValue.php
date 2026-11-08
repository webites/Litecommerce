<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property int $attribute_definition_id @property string $value_key @property string|null $color_hex @property int $position */
final class AttributeValue extends Model
{
    protected $fillable = ['attribute_definition_id', 'value_key', 'color_hex', 'position'];

    /** @return BelongsTo<AttributeDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class, 'attribute_definition_id');
    }

    /** @return HasMany<AttributeValueTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeValueTranslation::class);
    }
}
