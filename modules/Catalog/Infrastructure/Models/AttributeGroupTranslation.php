<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $attribute_group_id @property string $locale @property string $name */
final class AttributeGroupTranslation extends Model
{
    protected $fillable = ['attribute_group_id', 'locale', 'name'];
}
