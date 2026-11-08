<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Domain;

/** A shipping method that keeps its merchant settings in its own configuration instead of shipping_method_settings. */
interface HasOwnMerchantSettings
{
    public function merchantSettings(): MerchantShippingSettings;
}
