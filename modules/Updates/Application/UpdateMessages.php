<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Identity\Contracts\CorePermission;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceMode;
use Litecommerce\Modules\Maintenance\Contracts\MaintenanceReason;
use Litecommerce\Modules\Notifications\Contracts\PanelMessage;
use Litecommerce\Modules\Notifications\Contracts\PanelMessageSeverity;
use Litecommerce\Modules\Updates\Contracts\UpdateChecker;
use Litecommerce\Modules\Updates\Contracts\UpdateInstaller;

/** Dashboard and banner messages about core updates, derived from the stored check result and the last operation. */
final class UpdateMessages
{
    public const SOURCE = 'core.updates';

    public function __construct(
        private readonly UpdateChecker $updates,
        private readonly UpdateInstaller $installer,
        private readonly MaintenanceMode $maintenance,
    ) {}

    public static function availableKey(string $version): string
    {
        return self::SOURCE.'.available:'.$version;
    }

    /** @return list<PanelMessage> */
    public function messages(): array
    {
        $operation = $this->installer->current();
        $stuck = $operation?->status === 'failed' && $this->maintenance->state()?->reason === MaintenanceReason::Update;
        $messages = [];

        if ($stuck) {
            $messages[] = new PanelMessage(
                self::SOURCE.'.failed:'.$operation->id,
                PanelMessageSeverity::Danger,
                trans('updates::messages.failed_title', ['version' => $operation->targetVersion]),
                trans('updates::messages.failed_body'),
                trans('updates::messages.action'),
                $this->updatesUrl(),
                CorePermission::ManageUpdates->value,
            );
        }

        $status = $this->updates->status();
        $release = $status->availableRelease;

        if ($release !== null && ! $stuck && $operation?->status !== 'running') {
            $version = $release->version->toString();
            $messages[] = new PanelMessage(
                self::availableKey($version),
                PanelMessageSeverity::Warning,
                trans('updates::messages.available_title', ['version' => $version]),
                trans($status->compatibility->problems === [] ? 'updates::messages.available_body' : 'updates::messages.available_blocked_body'),
                trans('updates::messages.action'),
                $this->updatesUrl(),
                CorePermission::ManageUpdates->value,
            );
        }

        return $messages;
    }

    private function updatesUrl(): string
    {
        return route('panel.settings.index', [], false).'#updates';
    }
}
