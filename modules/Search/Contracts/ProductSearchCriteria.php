<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

final readonly class ProductSearchCriteria
{
    /** @param array<string, list<string>> $attributes */
    public function __construct(
        public string $query,
        public string $locale,
        public string $currencyCode,
        public ?int $categoryId,
        public ?string $minimumPrice,
        public ?string $maximumPrice,
        public bool $availableOnly,
        public array $attributes,
        public int $page,
        public int $perPage,
    ) {}
}
