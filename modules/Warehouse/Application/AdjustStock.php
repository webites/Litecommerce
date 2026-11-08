<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductTypeRegistry;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Warehouse\Contracts\StockAdjuster;
use Litecommerce\Modules\Warehouse\Contracts\StockMovementType;
use Litecommerce\Modules\Warehouse\Domain\Quantity;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockMovement;
use Litecommerce\Modules\Warehouse\Infrastructure\StockItemLocator;

final class AdjustStock implements StockAdjuster
{
    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly StockItemLocator $locator,
        private readonly ProductTypeRegistry $productTypes,
    ) {}

    public function setAllowBackorder(int $variantId, bool $allowed): void
    {
        $this->assertPhysicalVariant($variantId);

        DB::transaction(function () use ($variantId, $allowed): void {
            $item = $this->locator->lockOrCreateForVariant($variantId);
            $item->update(['allow_backorder' => $allowed]);
        });
    }

    public function receive(int $variantId, string $quantity, ?string $reason, ?string $actorId): void
    {
        $this->applyMovement($variantId, StockMovementType::Receipt, $quantity, $reason, $actorId);
    }

    public function returnToStock(int $variantId, string $quantity, ?string $reason, ?string $actorId): void
    {
        $this->applyMovement($variantId, StockMovementType::Return, $quantity, $reason, $actorId);
    }

    public function correctOnHand(int $variantId, string $newOnHandQuantity, ?string $reason, ?string $actorId): void
    {
        $this->assertPhysicalVariant($variantId);

        DB::transaction(function () use ($variantId, $newOnHandQuantity, $reason, $actorId): void {
            $item = $this->locator->lockOrCreateForVariant($variantId);
            $delta = Quantity::sub($newOnHandQuantity, $item->on_hand_quantity);

            if (Quantity::isZero($delta)) {
                return;
            }

            $item->update(['on_hand_quantity' => $newOnHandQuantity]);
            $this->recordMovement($item->id, StockMovementType::Correction, $delta, $reason, $actorId);
        });
    }

    private function applyMovement(int $variantId, StockMovementType $type, string $quantity, ?string $reason, ?string $actorId): void
    {
        $this->assertPhysicalVariant($variantId);

        if (! Quantity::isPositive($quantity)) {
            throw new \InvalidArgumentException('Movement quantity must be positive.');
        }

        DB::transaction(function () use ($variantId, $type, $quantity, $reason, $actorId): void {
            $item = $this->locator->lockOrCreateForVariant($variantId);
            $item->update(['on_hand_quantity' => Quantity::add($item->on_hand_quantity, $quantity)]);
            $this->recordMovement($item->id, $type, $quantity, $reason, $actorId);
        });
    }

    private function recordMovement(int $stockItemId, StockMovementType $type, string $delta, ?string $reason, ?string $actorId): void
    {
        StockMovement::query()->create([
            'stock_item_id' => $stockItemId,
            'type' => $type->value,
            'quantity_delta' => $delta,
            'reason' => $reason,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ]);
    }

    private function assertPhysicalVariant(int $variantId): void
    {
        $variant = $this->variants->find($variantId);

        if ($variant === null) {
            throw new \InvalidArgumentException("Variant {$variantId} does not exist.");
        }

        if (! $this->productTypes->get($variant->productType)->tracksStock) {
            throw new \InvalidArgumentException("Variant {$variantId} has a product type that does not track stock.");
        }
    }
}
