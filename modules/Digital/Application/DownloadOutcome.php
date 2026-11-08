<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Application;

enum DownloadOutcome
{
    case Served;
    case NotFound;
    case Expired;
    case Revoked;
    case LimitExceeded;
    case Unavailable;
}
