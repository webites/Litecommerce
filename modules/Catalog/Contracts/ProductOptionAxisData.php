<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/**
 * One variant-defining axis of a product (e.g. "Kolor" or "Rozmiar" —
 * as opposed to descriptive attributes). Only
 * values actually used by at least one of the product's enabled variants
 * are included — never the full product_option_values dictionary, which
 * can drift ahead of what variants actually exist.
 */
final readonly class ProductOptionAxisData
{
    /** @param list<ProductOptionValueData> $values */
    public function __construct(
        public int $definitionId,
        public string $key,
        public string $name,
        public string $slug,
        public bool $isColor,
        public array $values,
    ) {}
}
