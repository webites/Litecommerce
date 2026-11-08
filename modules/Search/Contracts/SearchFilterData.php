<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Search\Contracts;

final readonly class SearchFilterData
{
    /** @param list<SearchFilterValueData> $values */
    public function __construct(public string $key, public string $name, public array $values) {}
}
