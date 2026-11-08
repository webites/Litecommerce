<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $category_id @property string $locale @property string $slug @property string $name @property string|null $description @property string|null $seo_title @property string|null $seo_description */
final class BlogCategoryTranslation extends Model
{
    protected $fillable = ['category_id', 'locale', 'slug', 'name', 'description', 'seo_title', 'seo_description'];

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }
}
