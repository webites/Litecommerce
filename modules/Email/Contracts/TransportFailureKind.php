<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

enum TransportFailureKind: string
{
    case Temporary = 'temporary_failure';
    case Permanent = 'permanent_failure';
    case Unknown = 'delivery_unknown';
}
