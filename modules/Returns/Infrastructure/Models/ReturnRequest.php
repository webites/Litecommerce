<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string $source
 * @property int|null $customer_account_id
 * @property string $email
 * @property string|null $comment
 * @property string $status
 * @property Carbon|null $created_at
 * @property-read int $pending_count
 */
final class ReturnRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return HasMany<ReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ReturnLine::class);
    }
}
