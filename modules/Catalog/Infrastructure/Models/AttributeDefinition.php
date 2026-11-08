<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $key @property string $type @property bool $is_filterable */
final class AttributeDefinition extends Model
{
    protected $fillable = ['key', 'type', 'is_filterable'];

    /** @return HasMany<AttributeDefinitionTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(AttributeDefinitionTranslation::class);
    }

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_filterable' => 'boolean'];
    }
}
