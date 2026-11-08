<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property int|null $featured_media_id
 * @property Carbon|null $publish_at
 * @property Carbon|null $archived_at
 */
final class BlogPost extends Model
{
    protected $fillable = ['status', 'featured_media_id', 'publish_at', 'archived_at'];

    /** @return HasMany<BlogPostTranslation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany(BlogPostTranslation::class, 'post_id');
    }

    /** @return BelongsToMany<BlogCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category_post', 'post_id', 'category_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['publish_at' => 'datetime', 'archived_at' => 'datetime'];
    }
}
