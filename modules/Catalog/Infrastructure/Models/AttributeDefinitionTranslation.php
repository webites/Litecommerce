<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $attribute_definition_id @property string $locale @property string $name @property string $slug */
final class AttributeDefinitionTranslation extends Model
{
    protected $fillable = ['attribute_definition_id', 'locale', 'name', 'slug'];
}
