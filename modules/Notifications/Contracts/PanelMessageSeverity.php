<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Notifications\Contracts;

enum PanelMessageSeverity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';

    public function rank(): int
    {
        return match ($this) {
            self::Danger => 3,
            self::Warning => 2,
            self::Info => 1,
        };
    }
}
