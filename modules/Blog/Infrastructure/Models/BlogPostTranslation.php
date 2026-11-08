<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $post_id @property string $locale @property string $slug @property string $title @property string|null $excerpt @property string $content_html @property string|null $seo_title @property string|null $seo_description */
final class BlogPostTranslation extends Model
{
    protected $fillable = ['post_id', 'locale', 'slug', 'title', 'excerpt', 'content_html', 'seo_title', 'seo_description'];

    /** @return BelongsTo<BlogPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'post_id');
    }
}
