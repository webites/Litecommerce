<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $currency_code
 * @property string $beneficiary_name
 * @property string $account_number
 * @property array<string, string>|null $instructions
 * @property bool $enabled
 */
final class BankTransferAccount extends Model
{
    protected $primaryKey = 'currency_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['instructions' => 'array', 'enabled' => 'boolean'];
    }
}
