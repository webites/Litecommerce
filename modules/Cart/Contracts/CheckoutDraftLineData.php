<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

final readonly class CheckoutDraftLineData
{
    public function __construct(
        public int $variantId,
        public string $sku,
        public string $displayName,
        public string $productType,
        public string $quantity,
        public string $unitAmount,
        public string $lineNetAmount,
        public string $lineVatAmount,
        public string $lineGrossAmount,
    ) {}
}
