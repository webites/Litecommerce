<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

final readonly class AdjustmentContext
{
    /** @param  list<AdjustmentLineData>  $lines  Product lines after coupons, before any adjustment. */
    public function __construct(
        public string $currencyCode,
        public string $locale,
        public ?int $customerAccountId,
        public array $lines,
        public string $goodsGrossAmount,
    ) {}
}
