<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Blog\Contracts\BlogStatus;
use Litecommerce\Modules\Blog\Contracts\Events\BlogContentChanged;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogCategory;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPost;
use Litecommerce\Modules\Blog\Infrastructure\Models\BlogPostTranslation;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;

final readonly class SaveBlogPost
{
    public function __construct(private HtmlContentFilter $htmlFilter) {}

    public function handle(?BlogPost $post, SaveBlogPostData $data): BlogPost
    {
        $post = DB::transaction(function () use ($post, $data): BlogPost {
            $post ??= new BlogPost;
            $post->forceFill([
                'status' => $data->status,
                'publish_at' => $data->status === BlogStatus::Published->value ? $data->publishAt : null,
                'archived_at' => $data->status === BlogStatus::Archived->value ? now() : null,
            ]);
            $post->save();

            BlogPostTranslation::query()->updateOrCreate(
                ['post_id' => $post->getKey(), 'locale' => $data->locale],
                [
                    'slug' => $data->slug,
                    'title' => $data->title,
                    'excerpt' => $data->excerpt,
                    'content_html' => (string) $this->htmlFilter->clean($data->contentHtml),
                    'seo_title' => $data->seoTitle,
                    'seo_description' => $data->seoDescription,
                ],
            );

            $categoryIds = BlogCategory::query()
                ->whereNull('archived_at')
                ->whereKey($data->categoryIds)
                ->pluck('id')
                ->all();
            $post->categories()->sync($categoryIds);

            return $post->refresh();
        });

        DB::afterCommit(fn () => CommittedEvent::dispatch(new BlogContentChanged));

        return $post;
    }
}
