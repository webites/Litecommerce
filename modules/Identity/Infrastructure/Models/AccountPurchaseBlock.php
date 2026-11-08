<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_account_id',
    'reason',
    'blocked_at',
    'lifted_at',
    'created_by_staff_account_id',
])]
final class AccountPurchaseBlock extends Model
{
    /** @return BelongsTo<CustomerAccount, $this> */
    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    /** @return BelongsTo<StaffAccount, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(StaffAccount::class, 'created_by_staff_account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }
}
