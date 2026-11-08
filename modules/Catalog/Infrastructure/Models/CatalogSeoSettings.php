<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** @property string $default_variant_seo_mode */
final class CatalogSeoSettings extends Model
{
    protected $table = 'catalog_seo_settings';

    protected $guarded = [];

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }
}
