<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

enum PriceMode: string
{
    case Net = 'net';
    case Gross = 'gross';
}
