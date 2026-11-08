<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

enum ProductType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
}
