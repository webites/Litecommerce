<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * A shipping method offered at checkout — either one of the configurations
 * managed in the Delivery settings tab (provider "core") or one registered
 * by a plugin. Mirrors `Payments\Contracts\PaymentMethod`: a failing
 * implementation never yields a purchasable option, it makes the method
 * unavailable.
 *
 * A plugin method stays inactive until the merchant enables it in the
 * Delivery settings tab, where tax rate and COD/pay-on-pickup links are set.
 */
interface ShippingMethod
{
    public function descriptor(): ShippingMethodDescriptor;

    public function available(DeliveryQuoteContext $context): bool;

    public function quote(DeliveryQuoteContext $context, string $locale): ?ShippingMethodQuote;
}
