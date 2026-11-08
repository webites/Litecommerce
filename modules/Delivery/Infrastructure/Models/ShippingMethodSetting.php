<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $method_id
 * @property bool $active
 * @property int $position
 * @property int|null $tax_rate_id
 * @property bool $allow_cash_on_delivery
 * @property bool $allow_pay_on_pickup
 * @property int|null $pickup_deadline_days
 */
final class ShippingMethodSetting extends Model
{
    protected $primaryKey = 'method_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['method_id', 'active', 'position', 'tax_rate_id', 'allow_cash_on_delivery', 'allow_pay_on_pickup', 'pickup_deadline_days'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'allow_cash_on_delivery' => 'boolean', 'allow_pay_on_pickup' => 'boolean'];
    }
}
