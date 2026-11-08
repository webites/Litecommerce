<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $category_id @property string $locale @property string $name */
final class CategoryTranslation extends Model
{
    protected $fillable = ['category_id', 'locale', 'name', 'slug', 'description_html'];
}
