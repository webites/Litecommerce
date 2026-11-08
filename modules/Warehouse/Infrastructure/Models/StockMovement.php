<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_item_id
 * @property string $type
 * @property string $quantity_delta
 * @property string|null $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $actor_id
 */
final class StockMovement extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'stock_item_id', 'type', 'quantity_delta', 'reason',
        'reference_type', 'reference_id', 'actor_id', 'occurred_at',
    ];

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:6',
            'occurred_at' => 'datetime',
        ];
    }
}
