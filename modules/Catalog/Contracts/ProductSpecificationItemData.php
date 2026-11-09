<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/** One descriptive attribute of a product, already formatted for display in the requested locale. */
final readonly class ProductSpecificationItemData
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
