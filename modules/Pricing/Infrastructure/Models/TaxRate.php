<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $kind
 * @property string|null $percentage
 */
final class TaxRate extends Model
{
    protected $table = 'tax_rates';

    protected $fillable = ['name', 'kind', 'percentage'];

    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:2',
        ];
    }
}
