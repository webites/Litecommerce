<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Infrastructure\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_account_id',
    'label',
    'recipient_name',
    'company_name',
    'country_code',
    'postal_code',
    'city',
    'region',
    'address_line_1',
    'address_line_2',
    'phone',
    'is_default_billing',
    'is_default_shipping',
])]
final class AddressBookEntry extends Model
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
            'is_default_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
        ];
    }
}
