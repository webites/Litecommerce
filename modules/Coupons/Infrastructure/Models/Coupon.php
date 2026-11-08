<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $code
 * @property string $status
 * @property int|null $customer_account_id
 * @property string|null $pin_hash
 * @property int $failed_pin_attempts
 * @property Carbon|null $pin_locked_at
 * @property Carbon|null $expires_at
 * @property bool $combinable_with_promotional
 * @property bool $combinable_with_other_coupons
 * @property bool $applies_to_delivery
 * @property string $balance
 * @property string $reserved_amount
 * @property string|null $note
 */
final class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pin_locked_at' => 'datetime',
            'expires_at' => 'datetime',
            'combinable_with_promotional' => 'boolean',
            'combinable_with_other_coupons' => 'boolean',
            'applies_to_delivery' => 'boolean',
            'balance' => 'decimal:6',
            'reserved_amount' => 'decimal:6',
        ];
    }

    public function availableBalance(): string
    {
        return bcsub((string) $this->balance, (string) $this->reserved_amount, 6);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPinLocked(): bool
    {
        return $this->pin_locked_at !== null;
    }
}
