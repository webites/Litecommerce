<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Catalog\Application;

use Litecommerce\Modules\Catalog\Infrastructure\Models\ProductVariant;
use RuntimeException;

final class SkuGenerator
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $sku = 'LC-'.strtoupper(bin2hex(random_bytes(6)));

            if (! ProductVariant::query()->where('sku', $sku)->exists()) {
                return $sku;
            }
        }

        throw new RuntimeException('Nie udało się wygenerować unikalnego SKU.');
    }
}
