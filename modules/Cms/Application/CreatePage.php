<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;

final class CreatePage
{
    public function handle(CreatePageData $data): CmsPage
    {
        return DB::transaction(function () use ($data): CmsPage {
            $page = CmsPage::query()->create([
                'status' => $data->status,
                'publish_at' => $data->status === PageStatus::Published->value ? now() : null,
                'archived_at' => $data->status === PageStatus::Archived->value ? now() : null,
                'is_homepage' => false,
            ]);

            CmsPageTranslation::query()->create([
                'page_id' => $page->getKey(),
                'locale' => $data->locale,
                'slug' => $data->slug,
                'title' => $data->title,
                'seo_title' => $data->seoTitle,
                'seo_description' => $data->seoDescription,
                'content' => ['content' => [], 'root' => ['props' => []]],
            ]);

            return $page;
        });
    }
}
