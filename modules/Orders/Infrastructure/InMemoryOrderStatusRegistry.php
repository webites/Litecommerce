<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Infrastructure;

use Litecommerce\Modules\Orders\Contracts\OrderStatusDefinition;
use Litecommerce\Modules\Orders\Contracts\OrderStatusRegistry;

final class InMemoryOrderStatusRegistry implements OrderStatusRegistry
{
    /** @var array<string, OrderStatusDefinition> */
    private array $statuses = [];

    public function register(OrderStatusDefinition $status): void
    {
        if (! preg_match('/^[a-z][a-z0-9_.-]{1,31}$/', $status->key)) {
            throw new \InvalidArgumentException("Invalid order status key [{$status->key}].");
        }
        if (isset($this->statuses[$status->key])) {
            throw new \InvalidArgumentException("Order status [{$status->key}] is already registered.");
        }
        $this->statuses[$status->key] = $status;
    }

    public function has(string $key): bool
    {
        return isset($this->statuses[$key]);
    }

    public function all(): array
    {
        return array_values($this->statuses);
    }
}
