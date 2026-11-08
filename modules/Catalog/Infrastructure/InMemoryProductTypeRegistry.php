<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Infrastructure;

use Litecommerce\Modules\Catalog\Contracts\ProductTypeDescriptor;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;

final class InMemoryProductTypeRegistry implements ProductTypeRegistry
{
    /** @var array<string, ProductTypeDescriptor> */
    private array $types = [];

    public function register(ProductTypeDescriptor $type): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{1,31}$/', $type->key)) {
            throw new \InvalidArgumentException("Invalid product type key [{$type->key}].");
        }
        if (isset($this->types[$type->key])) {
            throw new \InvalidArgumentException("Product type [{$type->key}] is already registered.");
        }
        $this->types[$type->key] = $type;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): ProductTypeDescriptor
    {
        return $this->types[$key] ?? ProductTypeDescriptor::unregistered($key);
    }

    public function all(): array
    {
        return array_values($this->types);
    }
}
