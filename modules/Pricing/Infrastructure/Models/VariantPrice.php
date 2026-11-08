<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $variant_id
 * @property string $regular_amount
 * @property string $regular_mode
 * @property string|null $promotional_amount
 * @property string|null $promotional_mode
 */
final class VariantPrice extends Model
{
    protected $table = 'variant_prices';

    protected $primaryKey = 'variant_id';

    public $incrementing = false;

    protected $fillable = ['variant_id', 'regular_amount', 'regular_mode', 'promotional_amount', 'promotional_mode'];

    protected function casts(): array
    {
        return [
            'regular_amount' => 'decimal:6',
            'promotional_amount' => 'decimal:6',
        ];
    }
}
