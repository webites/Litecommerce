<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $variant_id
 * @property int $warehouse_id
 * @property string $on_hand_quantity
 * @property string $reserved_quantity
 * @property bool $allow_backorder
 */
final class StockItem extends Model
{
    protected $fillable = ['variant_id', 'warehouse_id', 'on_hand_quantity', 'reserved_quantity', 'allow_backorder'];

    /** @return HasMany<StockReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'decimal:6',
            'reserved_quantity' => 'decimal:6',
            'allow_backorder' => 'boolean',
        ];
    }
}
