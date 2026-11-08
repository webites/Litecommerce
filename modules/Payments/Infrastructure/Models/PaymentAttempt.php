<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $order_id
 * @property string $provider_id
 * @property string $method_id
 * @property string|null $provider_transaction_id
 * @property string $status
 * @property string $amount
 * @property string $currency_code
 * @property string $mode
 * @property array<string, mixed>|null $metadata
 */
final class PaymentAttempt extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:6', 'paid_at' => 'datetime', 'last_checked_at' => 'datetime', 'metadata' => 'array',
        ];
    }
}
