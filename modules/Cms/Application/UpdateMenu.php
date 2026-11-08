<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;

/**
 * At most one menu holds a given location_id — enforced here in a transaction
 * (same "swap in a transaction" approach as Cms\Application\SetHomepage) plus
 * the column's own unique index as a safety net, since assigning a location
 * to this menu must silently take it away from whichever menu had it before.
 */
final class UpdateMenu
{
    public function handle(Menu $menu, string $name, ?string $locationId): void
    {
        DB::transaction(function () use ($menu, $name, $locationId): void {
            if ($locationId !== null) {
                Menu::query()->where('location_id', $locationId)->where('id', '!=', $menu->getKey())->update(['location_id' => null]);
            }

            $menu->update(['name' => $name, 'location_id' => $locationId]);
        });

        DB::afterCommit(static fn () => CommittedEvent::dispatch(new CmsContentChanged));
    }
}
