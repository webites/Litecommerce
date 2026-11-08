<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

enum ThemeStatus: string
{
    case Installed = 'installed';
    case Active = 'active';
}
