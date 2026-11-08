<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;

/**
 * "Reset to theme default" for one archetype — deletes ONLY the given row
 * (cascades to its cms_page_translations via the FK), never touches any
 * other archetype's row or plain Cms pages. Unlike ArchivePage (which keeps
 * the row around, archived), an archetype override has no authoring history
 * worth keeping once reset: the next edit starts from a clean slate via
 * GetOrCreateArchetypeTemplate.
 */
final class DeleteArchetypeTemplate
{
    public function handle(CmsPage $page): void
    {
        $page->delete();
    }
}
