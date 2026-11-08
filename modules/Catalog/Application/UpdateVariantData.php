<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

final readonly class UpdateVariantData
{
    public function __construct(
        public string $locale,
        public string $sku,
        public ?string $gtin,
        public ?string $mpn,
        public bool $enabled,
        public ?string $name,
        public ?string $shortDescription,
        public ?string $descriptionHtml,
        public ?int $weightGrams,
        public ?int $lengthMm,
        public ?int $widthMm,
        public ?int $heightMm,
    ) {}
}
