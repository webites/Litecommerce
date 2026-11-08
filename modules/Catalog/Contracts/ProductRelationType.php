<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

enum ProductRelationType: string
{
    case Upsell = 'upsell';
    case CrossSell = 'cross_sell';
}
