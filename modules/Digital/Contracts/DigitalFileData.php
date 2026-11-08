<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

final readonly class DigitalFileData
{
    public function __construct(
        public int $id,
        public int $variantId,
        public string $label,
        public bool $active,
        public ?int $maxDownloads,
        public ?int $validityDays,
        public int $position,
        public ?DigitalAssetVersionData $currentVersion,
    ) {}
}
