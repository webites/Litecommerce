<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

interface PricingSettingsCatalog
{
    /**
     * The shop-wide net/gross price entry mode (DONE-007). Consumers that
     * do not keep their own historical per-row mode, unlike catalog
     * variant prices, always interpret their stored amounts under the
     * current value of this setting.
     */
    public function entryMode(): PriceMode;
}
