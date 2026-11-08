<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Blog\Contracts;

enum BlogStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
