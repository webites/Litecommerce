<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Coupons\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/** @property bool $enabled */
final class CouponSettings extends Model
{
    protected $table = 'coupon_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }
}
