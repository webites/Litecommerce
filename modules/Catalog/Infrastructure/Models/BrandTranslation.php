<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $brand_id @property string $locale @property string $name */
final class BrandTranslation extends Model
{
    protected $fillable = ['brand_id', 'locale', 'name', 'slug', 'description_html'];
}
