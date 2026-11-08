<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Contracts;

final readonly class PaymentMethodDescriptor
{
    public function __construct(
        public string $id,
        public string $providerId,
        public string $name,
        public string $kind,
        public bool $supportsPaymentLink = false,
    ) {}
}
