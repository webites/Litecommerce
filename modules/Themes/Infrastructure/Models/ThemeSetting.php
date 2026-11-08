<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $theme_id
 * @property string $key
 * @property string|null $value
 */
final class ThemeSetting extends Model
{
    protected $guarded = [];
}
