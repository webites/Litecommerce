<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Brand extends Model
{
    protected $fillable = ['logo_media_id'];

    /** @return HasMany<BrandTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BrandTranslation::class);
    }
}
