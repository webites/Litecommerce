<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Application;

use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockItem;

final readonly class WarehouseDemoDataContributor implements DemoDataContributor
{
    public function __construct(private StockAdjuster $stock, private DemoDataContent $demoContent) {}

    public function key(): string
    {
        return 'warehouse';
    }

    public function priority(): int
    {
        return 300;
    }

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void
    {
        $variantIds = $resources->ids($context->batchId, 'catalog', 'variant');
        if (count($variantIds) !== 30) {
            throw new \RuntimeException('Zestaw demonstracyjny produktów i stanów jest niespójny.');
        }

        $note = (string) $this->demoContent->load('warehouse::demo', $context->locale)['stock_note'];

        foreach ($variantIds as $index => $variantId) {
            $quantity = (string) (12 + (($index * 7) % 41));
            $this->stock->receive((int) $variantId, $quantity, $note, null);
            $stockItem = StockItem::query()->where('variant_id', (int) $variantId)->firstOrFail();
            $resources->record($context->batchId, $this->key(), 'stock_item', (int) $stockItem->getKey());
        }
    }

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void
    {
        StockItem::query()->whereKey(array_map('intval', $resources->ids($batchId, $this->key(), 'stock_item')))->delete();
    }
}
