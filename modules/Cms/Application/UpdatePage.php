<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;

final class UpdatePage
{
    public function handle(CmsPage $page, CreatePageData $data): CmsPage
    {
        return DB::transaction(function () use ($page, $data): CmsPage {
            $publishedAt = $page->publish_at;

            if ($data->status === PageStatus::Published->value && $publishedAt === null) {
                $publishedAt = now();
            }

            $page->update([
                'status' => $data->status,
                'publish_at' => $publishedAt,
                'archived_at' => $data->status === PageStatus::Archived->value ? now() : null,
            ]);

            // firstOrNew, not updateOrCreate: "content" has no DB default (NOT NULL,
            // JSON), so a brand new locale needs an explicit empty Puck document —
            // an existing one must keep whatever the page builder already saved.
            $translation = CmsPageTranslation::query()->firstOrNew(['page_id' => $page->getKey(), 'locale' => $data->locale]);
            $translation->slug = $data->slug;
            $translation->title = $data->title;
            $translation->seo_title = $data->seoTitle;
            $translation->seo_description = $data->seoDescription;

            if (! $translation->exists) {
                $translation->content = ['content' => [], 'root' => ['props' => []]];
            }

            $translation->save();

            return $page->refresh();
        });
    }
}
