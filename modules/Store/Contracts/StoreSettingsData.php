<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Store\Contracts;

final readonly class StoreSettingsData
{
    public function __construct(
        public string $storeName,
        public ?int $logoMediaId,
        public ?string $logoUrl,
        public ?string $logoSrcset,
        public ?int $logoWidth,
        public ?int $logoHeight,
        public bool $blogEnabled,
        public int $catalogProductsPerPage,
        public string $timezone = 'UTC',
    ) {}
}
