<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Litecommerce\Modules\Delivery\Infrastructure\ConfiguredShippingMethod;

/**
 * @property int $id
 * @property string $kind
 * @property string $name
 * @property bool $active
 * @property int $position
 * @property string|null $price_amount
 * @property string|null $free_threshold_amount
 * @property int|null $tax_rate_id
 * @property bool $supports_cash_on_delivery
 * @property bool $supports_pay_on_pickup
 * @property int|null $pickup_deadline_days
 */
final class DeliveryMethod extends Model
{
    protected $fillable = [
        'kind', 'name', 'active', 'position', 'price_amount', 'free_threshold_amount',
        'tax_rate_id', 'supports_cash_on_delivery', 'supports_pay_on_pickup', 'pickup_deadline_days',
    ];

    public function methodKey(): string
    {
        return ConfiguredShippingMethod::keyFor((int) $this->getKey());
    }

    /** @return HasMany<DeliveryMethodCurrencyOverride, $this> */
    public function currencyOverrides(): HasMany
    {
        return $this->hasMany(DeliveryMethodCurrencyOverride::class);
    }

    /** @return HasMany<DeliveryRegionRule, $this> */
    public function regionRules(): HasMany
    {
        return $this->hasMany(DeliveryRegionRule::class);
    }

    /** @return HasMany<DeliveryPickupPoint, $this> */
    public function pickupPoints(): HasMany
    {
        return $this->hasMany(DeliveryPickupPoint::class);
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean', 'supports_cash_on_delivery' => 'boolean', 'supports_pay_on_pickup' => 'boolean',
            'price_amount' => 'decimal:6', 'free_threshold_amount' => 'decimal:6',
        ];
    }
}
