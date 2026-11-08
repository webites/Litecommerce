<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Delivery\Contracts;

interface ShippingMethodRegistry
{
    /**
     * Registers a method provided in code. Its id must start with
     * "<providerId>." and the provider "core" is reserved.
     */
    public function register(ShippingMethod $method): void;

    public function addSource(ShippingMethodSource $source): void;

    public function has(string $methodId): bool;

    public function get(string $methodId): ShippingMethod;

    /** @return list<ShippingMethod> */
    public function methods(): array;

    /** @return list<ShippingMethodDescriptor> */
    public function all(): array;
}
