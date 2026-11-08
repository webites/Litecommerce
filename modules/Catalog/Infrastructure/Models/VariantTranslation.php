<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $product_variant_id
 * @property string $locale
 * @property string|null $name
 * @property string|null $short_description
 * @property string|null $description_html
 */
final class VariantTranslation extends Model
{
    protected $fillable = ['product_variant_id', 'locale', 'name', 'short_description', 'description_html'];
}
