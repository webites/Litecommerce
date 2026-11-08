<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

final class DemoDataResource extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'contributor', 'resource_type', 'resource_id', 'position'];
}
