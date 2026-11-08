<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $source_product_id
 * @property string $type
 * @property int|null $target_product_id
 * @property int|null $target_variant_id
 * @property int $position
 */
final class ProductRelation extends Model
{
    protected $fillable = ['source_product_id', 'type', 'target_product_id', 'target_variant_id', 'position'];
}
