<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

enum BuyerProfileType: string
{
    case Person = 'person';
    case Company = 'company';
}
