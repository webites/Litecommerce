<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Application;

use App\Support\Events\CommittedEvent;
use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Cms\Contracts\Events\CmsContentChanged;
use Litecommerce\Modules\Cms\Infrastructure\Models\Menu;

final class CreateMenu
{
    public function handle(string $name): Menu
    {
        $menu = Menu::query()->create(['name' => $name, 'location_id' => null, 'content' => []]);
        DB::afterCommit(static fn () => CommittedEvent::dispatch(new CmsContentChanged));

        return $menu;
    }
}
