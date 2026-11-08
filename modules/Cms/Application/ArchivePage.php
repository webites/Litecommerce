<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Litecommerce\Modules\Cms\Contracts\PageStatus;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;

final class ArchivePage
{
    public function handle(CmsPage $page): void
    {
        $page->update([
            'status' => PageStatus::Archived->value,
            'archived_at' => now(),
            'is_homepage' => false,
        ]);
    }
}
