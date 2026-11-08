<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $stock_item_id
 * @property string $quantity
 * @property string $state
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property Carbon|null $expires_at
 */
final class StockReservation extends Model
{
    protected $fillable = ['stock_item_id', 'quantity', 'state', 'reference_type', 'reference_id', 'expires_at'];

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'expires_at' => 'datetime',
        ];
    }
}
