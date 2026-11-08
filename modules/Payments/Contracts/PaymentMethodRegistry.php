<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentMethodRegistry
{
    public function register(PaymentMethod $method): void;

    public function get(string $methodId): PaymentMethod;

    /** @return list<PaymentMethodDescriptor> */
    public function all(): array;
}
