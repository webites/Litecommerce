<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Infrastructure\Repositories;

use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PricingSettingsCatalog;
use Litecommerce\Modules\Pricing\Infrastructure\Models\PricingSetting;

final class EloquentPricingSettingsCatalog implements PricingSettingsCatalog
{
    public function entryMode(): PriceMode
    {
        return PriceMode::from(PricingSetting::current()->price_entry_mode);
    }
}
