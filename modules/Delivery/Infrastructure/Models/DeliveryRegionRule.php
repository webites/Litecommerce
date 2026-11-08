<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $delivery_method_id
 * @property string $rule_type
 * @property string|null $country_code
 * @property string|null $continent_code
 * @property string|null $price_amount
 */
final class DeliveryRegionRule extends Model
{
    protected $fillable = ['delivery_method_id', 'rule_type', 'country_code', 'continent_code', 'price_amount'];

    /** @return HasMany<DeliveryRegionRuleCurrencyOverride, $this> */
    public function currencyOverrides(): HasMany
    {
        return $this->hasMany(DeliveryRegionRuleCurrencyOverride::class);
    }

    protected function casts(): array
    {
        return ['price_amount' => 'decimal:6'];
    }
}
