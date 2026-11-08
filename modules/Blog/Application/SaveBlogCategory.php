<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Blog\Contracts\Events\BlogContentChanged;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategory;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategoryTranslation;

final class SaveBlogCategory
{
    public function handle(?BlogCategory $category, SaveBlogCategoryData $data): BlogCategory
    {
        $category = DB::transaction(function () use ($category, $data): BlogCategory {
            $category ??= BlogCategory::query()->create();
            $category->archived_at = null;
            $category->save();

            BlogCategoryTranslation::query()->updateOrCreate(
                ['category_id' => $category->getKey(), 'locale' => $data->locale],
                [
                    'slug' => $data->slug,
                    'name' => $data->name,
                    'description' => $data->description,
                    'seo_title' => $data->seoTitle,
                    'seo_description' => $data->seoDescription,
                ],
            );

            return $category->refresh();
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new BlogContentChanged));

        return $category;
    }
}
