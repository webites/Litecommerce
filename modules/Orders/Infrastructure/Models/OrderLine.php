<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $variant_id
 * @property string $product_type
 * @property string $sku
 * @property string $display_name
 * @property string $quantity
 * @property string $line_net_amount
 * @property string $line_vat_amount
 * @property string $line_gross_amount
 * @property array<string, mixed>|null $allocations_snapshot
 * @property int|null $stock_reservation_id
 */
final class OrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6', 'unit_amount' => 'decimal:6',
            'line_net_amount' => 'decimal:6', 'line_vat_amount' => 'decimal:6', 'line_gross_amount' => 'decimal:6',
            'options_snapshot' => 'array', 'allocations_snapshot' => 'array',
        ];
    }
}
