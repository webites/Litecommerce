<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Redirects\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $locale
 * @property string $from_path
 * @property string $to_path
 */
final class Redirect extends Model
{
    protected $table = 'redirects';

    protected $fillable = ['locale', 'from_path', 'to_path'];
}
