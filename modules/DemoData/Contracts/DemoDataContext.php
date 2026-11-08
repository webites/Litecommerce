<?php

declare(strict_types=1);

namespace Litecommerce\Modules\DemoData\Contracts;

final readonly class DemoDataContext
{
    public function __construct(
        public string $batchId,
        public string $locale,
        public string $currencyCode,
    ) {}
}
