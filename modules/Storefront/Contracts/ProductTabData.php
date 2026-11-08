<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Storefront\Contracts;

/** One plugin-registered tab on the product page, already rendered — see ProductTabRegistry. */
final readonly class ProductTabData
{
    public function __construct(
        public string $id,
        public string $label,
        public string $html,
    ) {}
}
