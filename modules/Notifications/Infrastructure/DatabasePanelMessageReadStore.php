<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Infrastructure;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Notifications\Application\PanelMessageReadStore;

final class DatabasePanelMessageReadStore implements PanelMessageReadStore
{
    public function readKeys(int $staffId, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        return DB::table('panel_message_reads')
            ->where('staff_id', $staffId)
            ->whereIn('message_key', $keys)
            ->pluck('message_key')
            ->all();
    }

    public function markRead(int $staffId, string $key): void
    {
        DB::table('panel_message_reads')->insertOrIgnore(['staff_id' => $staffId, 'message_key' => $key, 'read_at' => now()]);
    }
}
