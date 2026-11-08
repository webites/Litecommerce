<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure;

use InvalidArgumentException;
use Litecommerce\Modules\Payments\Contracts\PaymentMethod;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodDescriptor;
use Litecommerce\Modules\Payments\Contracts\PaymentMethodRegistry;

final class InMemoryPaymentMethodRegistry implements PaymentMethodRegistry
{
    /** @var array<string, PaymentMethod> */
    private array $methods = [];

    public function register(PaymentMethod $method): void
    {
        $id = $method->descriptor()->id;
        if (isset($this->methods[$id])) {
            throw new InvalidArgumentException("Payment method [{$id}] is already registered.");
        }
        $this->methods[$id] = $method;
    }

    public function get(string $methodId): PaymentMethod
    {
        return $this->methods[$methodId] ?? throw new InvalidArgumentException("Unknown payment method [{$methodId}].");
    }

    public function all(): array
    {
        return array_map(static fn (PaymentMethod $method): PaymentMethodDescriptor => $method->descriptor(), array_values($this->methods));
    }
}
