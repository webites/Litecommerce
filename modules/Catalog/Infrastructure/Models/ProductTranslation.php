<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $product_id
 * @property string $locale
 * @property string $name
 * @property string $slug
 * @property string|null $short_description
 * @property string|null $description_html
 */
final class ProductTranslation extends Model
{
    protected $fillable = ['product_id', 'locale', 'name', 'slug', 'short_description', 'description_html'];
}
