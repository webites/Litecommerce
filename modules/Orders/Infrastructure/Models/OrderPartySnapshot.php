<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $order_id
 * @property string $buyer_type
 * @property string $email
 * @property string|null $company_name
 * @property string|null $tax_identifier
 * @property string|null $registration_number
 * @property string|null $phone
 * @property array<string, mixed> $billing_address
 * @property array<string, mixed>|null $shipping_address
 */
final class OrderPartySnapshot extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['billing_address' => 'array', 'shipping_address' => 'array'];
    }
}
