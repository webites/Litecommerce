<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Identity\Contracts;

final readonly class PurchaseBlockStatus
{
    private function __construct(
        public bool $blocked,
        public ?string $reason,
    ) {}

    public static function allowed(): self
    {
        return new self(false, null);
    }

    public static function blocked(?string $reason): self
    {
        return new self(true, $reason);
    }
}
