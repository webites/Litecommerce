<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;

final class DeleteMenu
{
    public function handle(Menu $menu): void
    {
        $menu->delete();
        DB::afterCommit(static fn () => CommittedEvent::dispatch(new CmsContentChanged));
    }
}
