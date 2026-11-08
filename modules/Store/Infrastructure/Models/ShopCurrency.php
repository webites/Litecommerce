<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimal_places
 * @property bool $is_base
 */
final class ShopCurrency extends Model
{
    protected $table = 'shop_currencies';

    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'is_base'];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_base' => 'boolean',
    ];
}
