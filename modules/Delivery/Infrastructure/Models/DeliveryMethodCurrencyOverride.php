<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $delivery_method_id
 * @property string $currency_code
 * @property string|null $price_amount
 * @property string|null $free_threshold_amount
 */
final class DeliveryMethodCurrencyOverride extends Model
{
    protected $fillable = ['delivery_method_id', 'currency_code', 'price_amount', 'free_threshold_amount'];

    protected function casts(): array
    {
        return ['price_amount' => 'decimal:6', 'free_threshold_amount' => 'decimal:6'];
    }
}
