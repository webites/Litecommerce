<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

final readonly class AdjustmentLineData
{
    public function __construct(
        public int $variantId,
        public string $productType,
        public string $quantity,
        public string $grossAmount,
    ) {}
}
