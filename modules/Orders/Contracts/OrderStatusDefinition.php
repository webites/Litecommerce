<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Contracts;

final readonly class OrderStatusDefinition
{
    // $labelKey is a full "namespace::group.key" translation key, resolved lazily via label() — never
    // eagerly, since registration happens in ServiceProvider::boot(), before the request's locale is set.
    public function __construct(
        public string $key,
        public string $labelKey,
        public bool $customerVisible = false,
    ) {}

    public function label(): string
    {
        return trans($this->labelKey);
    }
}
