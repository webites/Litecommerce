<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Contracts;

final readonly class ReturnReasonData
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public bool $active,
    ) {}
}
