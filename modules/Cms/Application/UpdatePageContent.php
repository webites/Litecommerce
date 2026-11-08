<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPageTranslation;
use Litecommerce\Modules\Store\Contracts\HtmlContentFilter;

final class UpdatePageContent
{
    public function __construct(private readonly HtmlContentFilter $htmlFilter) {}

    /** @param array<string, mixed> $content raw Puck document ({content, root}), stored verbatim */
    public function handle(CmsPage $page, string $locale, array $content): void
    {
        CmsPageTranslation::query()
            ->where('page_id', $page->getKey())
            ->where('locale', $locale)
            ->update(['content' => $this->htmlFilter->cleanDocument($content)]);
    }
}
