<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts\Events;

final readonly class PricingIndexChanged
{
    /** @param list<int> $variantIds Empty means every indexed variant. */
    public function __construct(public array $variantIds = []) {}
}
