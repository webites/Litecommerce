<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

interface ProductSearchListingBuilder
{
    /** @param array<string, mixed> $query */
    public function build(string $locale, ?int $categoryId, array $query): ProductSearchListingData;
}
