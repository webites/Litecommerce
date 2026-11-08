<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Contracts;

/**
 * Read side for Catalog's product/variant edit screen — the same pattern
 * as Pricing\Contracts\VariantPriceCatalog and Warehouse\Contracts\StockLevelCatalog
 * consumed from ProductController.
 */
interface DigitalFileCatalog
{
    /** @return list<DigitalFileData> ordered by position */
    public function listForVariant(int $variantId): array;

    public function find(int $fileId): ?DigitalFileData;

    /**
     * Only active file slots with a current version — what a new
     * purchase is entitled to. Callers that need every slot regardless of
     * state (the panel) use listForVariant() instead.
     *
     * @return list<DigitalFileData>
     */
    public function listPurchasableForVariant(int $variantId): array;
}
