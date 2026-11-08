<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

/** A signed extra charge (positive) or discount (negative); net plus VAT must equal gross. */
final readonly class AdjustmentData
{
    public function __construct(
        public string $code,
        public string $label,
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
    ) {}
}
