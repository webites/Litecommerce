<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;
use Litecommerce\Modules\Store\Contracts\SanitizableHtmlContent;

final class StoredCatalogHtml implements SanitizableHtmlContent
{
    private const TABLES = ['product_translations', 'variant_translations', 'brand_translations', 'category_translations'];

    public function label(): string
    {
        return 'Katalog (opisy produktów, wariantów, marek, kategorii)';
    }

    public function sanitizeStored(HtmlContentFilter $filter): int
    {
        $changed = 0;
        foreach (self::TABLES as $table) {
            DB::table($table)->whereNotNull('description_html')->orderBy('id')->chunkById(200, function ($rows) use ($table, $filter, &$changed): void {
                foreach ($rows as $row) {
                    $clean = $filter->sanitize((string) $row->description_html);
                    if ($clean !== $row->description_html) {
                        DB::table($table)->where('id', $row->id)->update(['description_html' => $clean]);
                        $changed++;
                    }
                }
            });
        }

        return $changed;
    }
}
