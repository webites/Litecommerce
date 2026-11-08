<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

/**
 * A pure function of the checkout contents: same input, same output, no side
 * effects. It runs on every pricing and again when the order is placed, so
 * the result must be reproducible. An exception stops the checkout.
 */
interface PricingAdjustment
{
    /** @return list<AdjustmentData> */
    public function adjust(AdjustmentContext $context): array;
}
