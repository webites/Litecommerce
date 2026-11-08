<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Infrastructure;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\SanitizableHtmlContent;

final class StoredCmsHtml implements SanitizableHtmlContent
{
    public function label(): string
    {
        return 'CMS (strony i szablony archetypów)';
    }

    public function sanitizeStored(HtmlContentFilter $filter): int
    {
        $changed = 0;
        DB::table('cms_page_translations')->orderBy('id')->chunkById(100, function ($rows) use ($filter, &$changed): void {
            foreach ($rows as $row) {
                $document = json_decode((string) $row->content, true);
                if (! is_array($document)) {
                    continue;
                }
                $clean = $filter->cleanDocument($document, true);
                if ($clean !== $document) {
                    DB::table('cms_page_translations')->where('id', $row->id)->update(['content' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    $changed++;
                }
            }
        });

        return $changed;
    }
}
