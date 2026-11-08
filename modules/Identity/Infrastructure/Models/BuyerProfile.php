<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Litecommerce\Modules\Identity\Contracts\BuyerProfileType;

#[Fillable([
    'customer_account_id',
    'type',
    'first_name',
    'last_name',
    'company_name',
    'tax_identifier',
    'registration_number',
    'is_default',
])]
final class BuyerProfile extends Model
{
    /** @return BelongsTo<CustomerAccount, $this> */
    public function customerAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerAccount::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => BuyerProfileType::class,
            'is_default' => 'boolean',
        ];
    }
}
