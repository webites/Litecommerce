<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Application;

use Litecommerce\Modules\Delivery\Contracts\DeliverySetup;
use Litecommerce\Modules\Pricing\Contracts\TaxRateCatalog;

/** The delivery VAT follows the shop's only tax rate; with several rates the merchant picks one in Settings. */
final readonly class ConfigureDelivery implements DeliverySetup
{
    public function __construct(private ManageDeliveryMethods $methods, private TaxRateCatalog $taxRates) {}

    public function addLocalPickup(string $methodName, string $pointName, string $countryCode, string $postalCode, string $city, string $addressLine): void
    {
        $this->methods->createConfigured(
            $this->method('pickup', $methodName),
            [
                'name' => $pointName, 'country_code' => strtoupper($countryCode), 'postal_code' => $postalCode,
                'city' => $city, 'address_line_1' => $addressLine, 'active' => true,
            ],
            null,
        );
    }

    public function addCourier(string $methodName, string $countryCode, string $price): void
    {
        $this->methods->createConfigured(
            $this->method('flat_rate', $methodName),
            null,
            [['rule_type' => 'country_rate', 'country_code' => strtoupper($countryCode), 'continent_code' => null, 'price_amount' => $price, 'currency_overrides' => []]],
        );
    }

    /** @return array<string, mixed> */
    private function method(string $kind, string $name): array
    {
        $rates = $this->taxRates->all();

        return [
            'kind' => $kind, 'name' => $name, 'active' => true,
            'tax_rate_id' => count($rates) === 1 ? $rates[0]->id : null,
            'supports_cash_on_delivery' => false, 'supports_pay_on_pickup' => false,
        ];
    }
}
