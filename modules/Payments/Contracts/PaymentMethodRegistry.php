<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

interface PaymentMethodRegistry
{
    public function register(PaymentMethod $method): void;

    public function get(string $methodId): PaymentMethod;

    /** @return list<PaymentMethodDescriptor> */
    public function all(): array;

    /** Display name of a method for customers and staff; the id itself when the method is no longer registered (e.g. a removed plugin). */
    public function nameOf(string $methodId): string;
}
