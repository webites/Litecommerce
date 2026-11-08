<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

enum ProductUnit: string
{
    case Piece = 'piece';
    case Kilogram = 'kilogram';
    case Meter = 'meter';
}
