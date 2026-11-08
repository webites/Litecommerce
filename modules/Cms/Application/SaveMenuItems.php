<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;

/**
 * Replaces the whole item tree in one write — the same "wholesale replace"
 * idiom as Cms\Application\UpdatePageContent for Puck's page-builder content.
 * The tree's shape (allowed types, required fields per type) is validated in
 * Presentation (SaveMenuItemsRequest) before this is ever called — there is
 * no per-item database constraint to catch a malformed node once it's just
 * JSON, so that validation is this feature's one real cost for not using a
 * normalized table.
 */
final class SaveMenuItems
{
    /** @param  array<int, array<string, mixed>>  $tree */
    public function handle(Menu $menu, array $tree): void
    {
        $menu->update(['content' => $tree]);
        DB::afterCommit(static fn () => CommittedEvent::dispatch(new CmsContentChanged));
    }
}
