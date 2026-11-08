<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

enum MenuItemType: string
{
    case Page = 'page';
    case Archetype = 'archetype';
    case Widget = 'widget';
    case Custom = 'custom';
}
