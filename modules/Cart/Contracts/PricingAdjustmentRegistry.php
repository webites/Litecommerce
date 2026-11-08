<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

interface PricingAdjustmentRegistry
{
    /** Lower priority runs first; equal priorities keep registration order. */
    public function register(PricingAdjustment $adjustment, int $priority = 100): void;

    /** @return list<PricingAdjustment> */
    public function all(): array;
}
