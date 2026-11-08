<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

/**
 * A provider of shipping methods whose set changes at runtime (for example
 * rows edited in the panel), as opposed to a method registered once in code.
 */
interface ShippingMethodSource
{
    /** @return list<ShippingMethod> */
    public function methods(): array;

    public function find(string $methodId): ?ShippingMethod;
}
