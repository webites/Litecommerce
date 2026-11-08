<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
