<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $cart_id
 * @property string $code
 */
final class CartCoupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attached_at' => 'datetime'];
    }
}
