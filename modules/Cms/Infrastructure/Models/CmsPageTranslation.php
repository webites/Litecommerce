<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $page_id
 * @property string $locale
 * @property string $slug A synthetic, never-user-typeable placeholder for an archetype template row (see CmsPage::$archetype and GetOrCreateArchetypeTemplate) — it has no URL of its own.
 * @property string $title
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property array<string, mixed> $content
 */
final class CmsPageTranslation extends Model
{
    protected $fillable = ['page_id', 'locale', 'slug', 'title', 'seo_title', 'seo_description', 'content'];

    /** @return BelongsTo<CmsPage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'page_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
