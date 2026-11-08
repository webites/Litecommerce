<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Application;

use Litecommerce\Modules\Identity\Contracts\StaffPermissionChecker;
use Litecommerce\Modules\Notifications\Contracts\PanelInbox;
use Litecommerce\Modules\Notifications\Contracts\PanelMessage;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageRegistry;

final class StaffPanelInbox implements PanelInbox
{
    public function __construct(
        private readonly PanelMessageRegistry $registry,
        private readonly StaffPermissionChecker $permissions,
        private readonly PanelMessageReadStore $reads,
    ) {}

    public function active(int $staffId): array
    {
        $visible = array_values(array_filter(
            $this->registry->collect(),
            fn (PanelMessage $message): bool => $message->permission === null || $this->permissions->allows($staffId, $message->permission),
        ));

        usort($visible, static fn (PanelMessage $a, PanelMessage $b): int => $b->severity->rank() <=> $a->severity->rank());

        return $visible;
    }

    public function unread(int $staffId): array
    {
        $active = $this->active($staffId);
        $read = array_flip($this->reads->readKeys($staffId, array_map(static fn (PanelMessage $m): string => $m->key, $active)));

        return array_values(array_filter($active, static fn (PanelMessage $message): bool => ! isset($read[$message->key])));
    }

    public function markRead(int $staffId, string $key): bool
    {
        foreach ($this->active($staffId) as $message) {
            if ($message->key === $key) {
                $this->reads->markRead($staffId, $key);

                return true;
            }
        }

        return false;
    }
}
