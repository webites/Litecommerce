<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How many base-currency units equal one unit of `currency_code`
 * (e.g. currency_code=EUR, base_units_per_unit=4.320000 means 1 EUR = 4.32
 * units of the shop's base currency).
 *
 * @property int $id
 * @property string $currency_code
 * @property string $base_units_per_unit
 */
final class ExchangeRate extends Model
{
    protected $table = 'exchange_rates';

    protected $fillable = ['currency_code', 'base_units_per_unit'];

    protected function casts(): array
    {
        return [
            'base_units_per_unit' => 'decimal:6',
        ];
    }
}
