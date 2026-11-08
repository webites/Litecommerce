<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $status
 * @property string $variant_seo_mode
 * @property bool $featured
 * @property string $unit_code
 * @property string $minimum_quantity
 * @property string|null $maximum_quantity
 * @property string $quantity_step
 * @property int|null $brand_id
 * @property int|null $primary_category_id
 * @property Carbon|null $publish_at
 * @property Carbon|null $archived_at
 */
final class Product extends Model
{
    protected $fillable = [
        'type', 'status', 'variant_seo_mode', 'brand_id', 'primary_category_id', 'featured', 'unit_code',
        'minimum_quantity', 'maximum_quantity', 'quantity_step', 'publish_at', 'archived_at',
    ];

    /** @return HasMany<ProductTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsToMany<Category, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'minimum_quantity' => 'decimal:6',
            'maximum_quantity' => 'decimal:6',
            'quantity_step' => 'decimal:6',
            'publish_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }
}
