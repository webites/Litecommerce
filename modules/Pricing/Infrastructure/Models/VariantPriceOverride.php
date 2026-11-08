<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $currency_code
 * @property string|null $regular_amount
 * @property string|null $regular_mode
 * @property string|null $promotional_amount
 * @property string|null $promotional_mode
 */
final class VariantPriceOverride extends Model
{
    protected $table = 'variant_price_overrides';

    protected $fillable = ['variant_id', 'currency_code', 'regular_amount', 'regular_mode', 'promotional_amount', 'promotional_mode'];

    protected function casts(): array
    {
        return [
            'regular_amount' => 'decimal:6',
            'promotional_amount' => 'decimal:6',
        ];
    }
}
