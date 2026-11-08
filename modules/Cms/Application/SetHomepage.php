<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Infrastructure\Models\CmsPage;

/**
 * Exactly one page has is_homepage=true — MySQL/MariaDB have no partial
 * unique index, so the invariant is enforced here, in a transaction, not
 * by a database constraint (same approach as Store\Application\SetBaseCurrency).
 */
final class SetHomepage
{
    public function handle(CmsPage $page): void
    {
        DB::transaction(function () use ($page): void {
            CmsPage::query()->where('is_homepage', true)->where('id', '!=', $page->getKey())->update(['is_homepage' => false]);
            $page->update(['is_homepage' => true]);
        });
    }
}
