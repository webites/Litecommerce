<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $cart_id
 * @property int $cart_version_seen
 * @property string $status
 * @property string $buyer_type
 * @property int|null $customer_account_id
 * @property string|null $email
 * @property string|null $company_name
 * @property string|null $tax_identifier
 * @property string|null $registration_number
 * @property string|null $phone
 * @property string|null $notes
 * @property bool $marketing_consent
 * @property string $currency_code
 * @property array|null $billing_address
 * @property bool $ships_to_different_address
 * @property array|null $shipping_address
 * @property string|null $delivery_method_id
 * @property int|null $delivery_pickup_point_id
 * @property array|null $price_snapshot
 * @property string|null $price_hash
 * @property Carbon|null $confirmed_at
 * @property int $version
 */
final class CheckoutDraft extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'cart_id', 'cart_version_seen', 'status', 'buyer_type',
        'customer_account_id', 'email',
        'company_name', 'tax_identifier', 'registration_number', 'phone', 'notes', 'marketing_consent',
        'currency_code', 'billing_address', 'ships_to_different_address', 'shipping_address',
        'delivery_method_id', 'delivery_pickup_point_id',
        'price_snapshot', 'price_hash', 'confirmed_at', 'version',
    ];

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'price_snapshot' => 'array',
            'ships_to_different_address' => 'boolean',
            'marketing_consent' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }
}
