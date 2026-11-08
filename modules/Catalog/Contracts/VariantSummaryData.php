<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

final readonly class VariantSummaryData
{
    /** @param array<string, string> $optionValues variant-defining axis key => value key (e.g. ['kolor' => 'czerwony']); empty for a product with no option axes */
    public function __construct(
        public int $id,
        public int $productId,
        public string $sku,
        public string $gtin,
        public string $mpn,
        public string $displayName,
        public bool $enabled,
        public string $productType,
        public string $minimumQuantity,
        public ?string $maximumQuantity,
        public string $quantityStep,
        public array $optionValues,
        public ?string $shortDescription = null,
        public ?string $descriptionHtml = null,
    ) {}
}
