<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Setup\Application;

use Litecommerce\Modules\Delivery\Contracts\DeliverySetup;
use Litecommerce\Modules\DemoData\Contracts\DemoDataManager;
use Litecommerce\Modules\Email\Contracts\EmailSettings;
use Litecommerce\Modules\Pricing\Contracts\PriceMode;
use Litecommerce\Modules\Pricing\Contracts\PricingSetup;
use Litecommerce\Modules\Store\Contracts\StoreBootstrap;
use Litecommerce\Modules\Store\Contracts\StoreSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

/** Applies one wizard step through the contracts of the module that owns the data. */
final readonly class ConfigureStore
{
    public function __construct(
        private StoreBootstrap $store,
        private StoreSettingsStore $settings,
        private PricingSetup $pricing,
        private DeliverySetup $delivery,
        private EmailSettings $email,
        private DemoDataManager $demoData,
    ) {}

    public function language(string $code, string $name): void
    {
        $this->store->setDefaultLanguage($code, $name);
    }

    public function currency(string $code, string $name, string $symbol, int $decimalPlaces): void
    {
        $this->store->setBaseCurrency($code, $name, $symbol, $decimalPlaces);
    }

    public function profile(string $storeName): void
    {
        $this->settings->set(StoreSettingKeys::StoreName, $storeName);
    }

    public function pricing(PriceMode $mode, ?string $taxRateName, ?string $taxPercentage): void
    {
        $this->pricing->configure($mode, $taxRateName, $taxPercentage);
    }

    public function localPickup(string $methodName, string $pointName, string $countryCode, string $postalCode, string $city, string $addressLine): void
    {
        $this->delivery->addLocalPickup($methodName, $pointName, $countryCode, $postalCode, $city, $addressLine);
    }

    public function courier(string $methodName, string $countryCode, string $price): void
    {
        $this->delivery->addCourier($methodName, $countryCode, $price);
    }

    /** @param array<string, mixed> $values */
    public function email(array $values): void
    {
        $this->email->update($values + ['transport_key' => 'smtp']);
    }

    public function demoData(): int
    {
        return $this->demoData->install()->resourceCount;
    }
}
