<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class SearchDocumentAttributeData
{
    public function __construct(
        public string $definitionKey,
        public string $definitionName,
        public string $valueKey,
        public string $valueName,
        public bool $filterable,
    ) {}
}
