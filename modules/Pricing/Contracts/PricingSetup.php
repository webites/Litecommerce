<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

/** First-time pricing configuration used by the setup wizard. */
interface PricingSetup
{
    /** Sets the shop-wide price entry mode and, when given, adds a percentage tax rate unless one with the same name exists. */
    public function configure(PriceMode $mode, ?string $taxRateName = null, ?string $taxPercentage = null): void;
}
