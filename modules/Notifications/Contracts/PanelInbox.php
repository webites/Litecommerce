<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Contracts;

/** The messages one staff member may see; "read" is stored per staff member and hides the banner only, never the dashboard entry. */
interface PanelInbox
{
    /** @return list<PanelMessage> most severe first, limited to the permissions of the staff member */
    public function active(int $staffId): array;

    /** @return list<PanelMessage> */
    public function unread(int $staffId): array;

    /** Returns false when no such message is currently shown to the staff member. */
    public function markRead(int $staffId, string $key): bool;
}
