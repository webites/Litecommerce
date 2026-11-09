<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/** A named group of descriptive attributes; a null name is the section of attributes without a group. */
final readonly class ProductSpecificationGroupData
{
    /** @param list<ProductSpecificationItemData> $items */
    public function __construct(
        public ?string $name,
        public array $items,
    ) {}
}
