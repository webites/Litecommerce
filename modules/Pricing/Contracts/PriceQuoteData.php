<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

final readonly class PriceQuoteData
{
    public function __construct(
        public int $variantId,
        public string $currencyCode,
        public string $quantity,
        public string $unitEffectiveAmount,
        public PriceMode $unitEffectiveMode,
        public string $unitSource,
        public ?string $regularUnitAmount,
        public ?string $promotionalUnitAmount,
        public bool $promotionalActive,
        public ?string $promotionalDropReason,
        public string $lineNetAmount,
        public string $lineVatAmount,
        public string $lineGrossAmount,
        public TaxQualificationKind $taxQualificationKind,
        public ?string $taxPercentage,
    ) {}
}
