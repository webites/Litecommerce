<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

interface OrderStatusRegistry
{
    public function register(OrderStatusDefinition $status): void;

    public function has(string $key): bool;

    /** @return list<OrderStatusDefinition> */
    public function all(): array;
}
