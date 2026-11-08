<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Pricing\Contracts;

final readonly class TaxRateData
{
    public function __construct(
        public int $id,
        public string $name,
        public TaxQualificationKind $kind,
        public ?string $percentage,
    ) {}
}
