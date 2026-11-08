<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

enum ExtensionStatus: string
{
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case WindingDown = 'winding_down';
}
