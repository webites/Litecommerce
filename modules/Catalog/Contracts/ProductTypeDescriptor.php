<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/**
 * What a product type means to the rest of the shop. Other modules ask about
 * a capability and never compare with a type key, so a plugin can add a type
 * without changes to stock, delivery, orders or returns.
 */
final readonly class ProductTypeDescriptor
{
    public function __construct(
        public string $key,
        public string $labelKey,
        public bool $tracksStock,
        public bool $requiresDelivery,
        public bool $grantsDigitalAccess,
        public bool $soldAsSingleUnit,
    ) {}

    public static function unregistered(string $key): self
    {
        return new self($key, '', false, false, false, false);
    }
}
