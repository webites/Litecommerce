<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $delivery_region_rule_id
 * @property string $currency_code
 * @property string $price_amount
 */
final class DeliveryRegionRuleCurrencyOverride extends Model
{
    protected $fillable = ['delivery_region_rule_id', 'currency_code', 'price_amount'];

    protected function casts(): array
    {
        return ['price_amount' => 'decimal:6'];
    }
}
