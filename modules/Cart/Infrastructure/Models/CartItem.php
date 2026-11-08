<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $variant_id
 * @property string $quantity
 */
final class CartItem extends Model
{
    protected $fillable = ['cart_id', 'variant_id', 'quantity'];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
        ];
    }
}
