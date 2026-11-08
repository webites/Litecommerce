<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $extension_id
 * @property string $key
 * @property string|null $value
 */
final class ExtensionSetting extends Model
{
    protected $guarded = [];
}
