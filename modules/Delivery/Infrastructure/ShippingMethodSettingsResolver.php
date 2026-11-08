<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Infrastructure;

use Litecommerce\Modules\Delivery\Contracts\ShippingMethod;
use Litecommerce\Modules\Delivery\Domain\HasOwnMerchantSettings;
use Litecommerce\Modules\Delivery\Domain\MerchantShippingSettings;
use Litecommerce\Modules\Delivery\Infrastructure\Models\ShippingMethodSetting;

final class ShippingMethodSettingsResolver
{
    /**
     * A method without stored settings is inactive: a plugin method never
     * becomes purchasable until the merchant enables it.
     *
     * @param  list<ShippingMethod>  $methods
     * @return array<string, MerchantShippingSettings> keyed by method id
     */
    public function resolve(array $methods): array
    {
        $resolved = [];
        $stored = [];

        foreach ($methods as $method) {
            $id = $method->descriptor()->id;

            if ($method instanceof HasOwnMerchantSettings) {
                $resolved[$id] = $method->merchantSettings();
            } else {
                $stored[] = $id;
            }
        }

        if ($stored !== []) {
            $rows = ShippingMethodSetting::query()->whereIn('method_id', $stored)->get()->keyBy('method_id');

            foreach ($stored as $id) {
                $row = $rows->get($id);
                $resolved[$id] = $row === null ? MerchantShippingSettings::inactive() : new MerchantShippingSettings(
                    $row->active, $row->position, $row->tax_rate_id, $row->allow_cash_on_delivery, $row->allow_pay_on_pickup, $row->pickup_deadline_days,
                );
            }
        }

        return $resolved;
    }
}
