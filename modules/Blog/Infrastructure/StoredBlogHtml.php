<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Infrastructure;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\SanitizableHtmlContent;

final class StoredBlogHtml implements SanitizableHtmlContent
{
    public function label(): string
    {
        return 'Blog (treść wpisów)';
    }

    public function sanitizeStored(HtmlContentFilter $filter): int
    {
        $changed = 0;
        DB::table('blog_post_translations')->orderBy('id')->chunkById(200, function ($rows) use ($filter, &$changed): void {
            foreach ($rows as $row) {
                $clean = $filter->sanitize((string) $row->content_html);
                if ($clean !== $row->content_html) {
                    DB::table('blog_post_translations')->where('id', $row->id)->update(['content_html' => $clean]);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
