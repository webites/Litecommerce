<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class OrderPlacementLineData
{
    /** @param  list<array{code:string,amount:string,base_amount:string}>  $couponAllocations */
    public function __construct(
        public int $variantId,
        public string $productType,
        public string $sku,
        public string $displayName,
        public string $quantity,
        public string $unitAmount,
        public string $lineNetAmount,
        public string $lineVatAmount,
        public string $lineGrossAmount,
        public array $couponAllocations = [],
    ) {}
}
