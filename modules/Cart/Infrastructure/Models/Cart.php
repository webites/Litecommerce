<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $customer_account_id
 * @property string|null $guest_token
 * @property string $currency_code
 * @property string $status
 * @property int $version
 * @property Carbon $last_activity_at
 */
final class Cart extends Model
{
    protected $fillable = ['customer_account_id', 'guest_token', 'currency_code', 'status', 'version', 'last_activity_at'];

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
        ];
    }
}
