<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cms\Contracts;

enum PageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
