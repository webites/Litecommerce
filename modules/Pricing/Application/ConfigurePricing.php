<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PricingSetup;
use Litecommerce\Modules\Pricing\Contracts\TaxQualificationKind;
use Litecommerce\Modules\Pricing\Infrastructure\Models\PricingSetting;
use Litecommerce\Modules\Pricing\Infrastructure\Models\TaxRate;

final class ConfigurePricing implements PricingSetup
{
    public function configure(PriceMode $mode, ?string $taxRateName = null, ?string $taxPercentage = null): void
    {
        DB::transaction(function () use ($mode, $taxRateName, $taxPercentage): void {
            PricingSetting::current()->update(['price_entry_mode' => $mode->value]);

            if ($taxRateName !== null && $taxPercentage !== null) {
                TaxRate::query()->firstOrCreate(
                    ['name' => $taxRateName],
                    ['kind' => TaxQualificationKind::Percentage->value, 'percentage' => $taxPercentage],
                );
            }
        });
    }
}
