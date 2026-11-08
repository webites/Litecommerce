<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $delivery_method_id
 * @property string $name
 * @property string $country_code
 * @property string $postal_code
 * @property string $city
 * @property string|null $region
 * @property string $address_line_1
 * @property string|null $address_line_2
 * @property array<string, string>|null $instructions
 * @property bool $active
 * @property int $position
 */
final class DeliveryPickupPoint extends Model
{
    protected $fillable = [
        'delivery_method_id', 'name', 'country_code', 'postal_code', 'city', 'region',
        'address_line_1', 'address_line_2', 'instructions', 'active', 'position',
    ];

    protected function casts(): array
    {
        return ['instructions' => 'array', 'active' => 'boolean'];
    }
}
