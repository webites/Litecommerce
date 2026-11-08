<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $product_id
 * @property int $tax_rate_id
 */
final class ProductTaxAssignment extends Model
{
    protected $table = 'product_tax_assignments';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = ['product_id', 'tax_rate_id'];
}
