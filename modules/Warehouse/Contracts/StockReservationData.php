<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

final readonly class StockReservationData
{
    public function __construct(
        public int $id,
        public int $variantId,
        public string $quantity,
        public StockReservationState $state,
        public ?string $referenceType,
        public ?string $referenceId,
        public ?\DateTimeImmutable $expiresAt,
    ) {}
}
