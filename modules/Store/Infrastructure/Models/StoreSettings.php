<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class StoreSettings extends Model
{
    protected $table = 'store_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
