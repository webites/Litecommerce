<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

final readonly class DemoDataBatchData
{
    public function __construct(
        public string $id,
        public string $status,
        public string $locale,
        public string $currencyCode,
        public int $resourceCount,
        public ?string $errorMessage,
        public ?string $completedAt,
        public ?string $removedAt,
        public string $createdAt,
    ) {}
}
