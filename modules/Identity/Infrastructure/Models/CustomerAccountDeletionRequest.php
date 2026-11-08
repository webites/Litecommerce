<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_account_id
 * @property string $token_hash
 * @property Carbon $expires_at
 */
final class CustomerAccountDeletionRequest extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
