<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $status
 * @property string|null $active_key
 * @property string $locale
 * @property string $currency_code
 * @property string|null $error_message
 * @property Carbon|null $completed_at
 * @property Carbon|null $removed_at
 * @property Carbon $created_at
 * @property int|null $resources_count
 */
final class DemoDataBatch extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'status', 'active_key', 'locale', 'currency_code', 'error_message',
        'completed_at', 'removed_at',
    ];

    /** @return HasMany<DemoDataResource, $this> */
    public function resources(): HasMany
    {
        return $this->hasMany(DemoDataResource::class, 'batch_id');
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'removed_at' => 'datetime'];
    }
}
