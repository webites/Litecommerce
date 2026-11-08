<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = ['name'];
}
