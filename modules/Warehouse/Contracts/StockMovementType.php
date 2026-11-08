<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

enum StockMovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Return = 'return';
    case Correction = 'correction';
}
