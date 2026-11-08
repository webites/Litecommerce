<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id=1), seeded by the migration.
 *
 * @property int $id
 * @property string $price_entry_mode
 */
final class PricingSetting extends Model
{
    protected $table = 'pricing_settings';

    protected $fillable = ['price_entry_mode'];

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }
}
