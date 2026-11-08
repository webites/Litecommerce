<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon|null $archived_at
 */
final class BlogCategory extends Model
{
    protected $fillable = ['archived_at'];

    /** @return HasMany<BlogCategoryTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BlogCategoryTranslation::class, 'category_id');
    }

    /** @return BelongsToMany<BlogPost, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_category_post', 'category_id', 'post_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }
}
