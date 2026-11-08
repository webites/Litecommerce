<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Contracts;

/** One selectable value of a ProductOptionAxisData (e.g. "Czerwony" under the "Kolor" axis). */
final readonly class ProductOptionValueData
{
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public string $slug,
        public ?string $colorHex,
    ) {}
}
