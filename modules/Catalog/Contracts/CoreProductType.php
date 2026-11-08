<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

enum CoreProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
}
