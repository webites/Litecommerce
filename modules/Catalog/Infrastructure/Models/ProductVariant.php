<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $gtin
 * @property string|null $mpn
 * @property bool $enabled
 * @property bool $is_default
 * @property int|null $weight_grams
 * @property int|null $length_mm
 * @property int|null $width_mm
 * @property int|null $height_mm
 * @property Product $product
 */
final class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'sku', 'gtin', 'mpn', 'enabled', 'is_default', 'option_signature',
        'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'archived_at',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<VariantTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(VariantTranslation::class, 'product_variant_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
