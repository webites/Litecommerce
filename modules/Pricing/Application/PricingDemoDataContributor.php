<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Application;

use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\Pricing\Contracts\PricingSettingsCatalog;
use Litecommerce\Modules\Pricing\Contracts\VariantPriceManager;
use Litecommerce\Modules\Pricing\Infrastructure\Models\VariantPrice;

final readonly class PricingDemoDataContributor implements DemoDataContributor
{
    public function __construct(
        private VariantPriceManager $prices,
        private PricingSettingsCatalog $settings,
    ) {}

    public function key(): string
    {
        return 'pricing';
    }

    public function priority(): int
    {
        return 200;
    }

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void
    {
        $variantIds = $resources->ids($context->batchId, 'catalog', 'variant');
        $amounts = [
            '59.00', '69.00', '79.00', '55.00', '139.00',
            '159.00', '169.00', '179.00', '89.00', '39.00',
            '169.00', '219.00', '269.00', '129.00', '99.00',
            '289.00', '289.00', '289.00', '249.00', '199.00',
            '39.00', '49.00', '59.00', '69.00', '55.00',
            '79.00', '79.00', '79.00', '75.00', '85.00',
        ];

        if (count($variantIds) !== count($amounts)) {
            throw new \RuntimeException('Zestaw demonstracyjny produktów i cen jest niespójny.');
        }

        foreach ($variantIds as $index => $variantId) {
            $this->prices->setBasePrice((int) $variantId, $amounts[$index], $this->settings->entryMode(), null, null);
            $resources->record($context->batchId, $this->key(), 'variant_price', $variantId);
        }
    }

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void
    {
        VariantPrice::query()->whereIn('variant_id', array_map('intval', $resources->ids($batchId, $this->key(), 'variant_price')))->delete();
    }
}
