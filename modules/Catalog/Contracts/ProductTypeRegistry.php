<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

interface ProductTypeRegistry
{
    public function register(ProductTypeDescriptor $type): void;

    public function has(string $key): bool;

    /** An unregistered key (for example from a disabled plugin) returns an inert descriptor with no capabilities. */
    public function get(string $key): ProductTypeDescriptor;

    /** @return list<ProductTypeDescriptor> */
    public function all(): array;
}
