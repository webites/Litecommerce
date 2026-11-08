<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Application;

interface PanelMessageReadStore
{
    /**
     * @param  list<string>  $keys
     * @return list<string> the keys among $keys that the staff member has read
     */
    public function readKeys(int $staffId, array $keys): array;

    public function markRead(int $staffId, string $key): void;
}
