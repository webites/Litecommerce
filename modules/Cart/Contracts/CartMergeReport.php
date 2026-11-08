<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

final readonly class CartMergeReport
{
    /** @param list<CartMergeAdjustment> $adjustments */
    public function __construct(
        public bool $merged,
        public array $adjustments,
    ) {}

    public static function nothingToMerge(): self
    {
        return new self(false, []);
    }
}
