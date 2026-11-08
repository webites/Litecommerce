<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Catalog\Contracts\ProductType;
use Litecommerce\Modules\Catalog\Contracts\VariantCatalog;
use Litecommerce\Modules\Warehouse\Contracts\InsufficientStockException;
use Litecommerce\Modules\Warehouse\Contracts\InvalidReservationStateException;
use Litecommerce\Modules\Warehouse\Contracts\StockMovementType;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationData;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationManager;
use Litecommerce\Modules\Warehouse\Contracts\StockReservationState;
use Litecommerce\Modules\Warehouse\Domain\Quantity;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockItem;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockMovement;
use Litecommerce\Modules\Warehouse\Infrastructure\Models\StockReservation;
use Litecommerce\Modules\Warehouse\Infrastructure\StockItemLocator;

/**
 * Implements the reservation state machine from
 * docs/knowledge-base/06-fulfillment.md and the "Rezerwacje i
 * współbieżność" section of 05-orders.md, ahead of the Cart/Checkout/
 * Orders modules that will call it — see StockReservationManager.
 */
final class ManageStockReservations implements StockReservationManager
{
    public function __construct(
        private readonly VariantCatalog $variants,
        private readonly StockItemLocator $locator,
    ) {}

    public function reserve(
        int $variantId,
        string $quantity,
        string $referenceType,
        string $referenceId,
        ?\DateTimeImmutable $expiresAt,
    ): StockReservationData {
        if (! Quantity::isPositive($quantity)) {
            throw new \InvalidArgumentException('Reservation quantity must be positive.');
        }

        $variant = $this->variants->find($variantId);

        if ($variant === null || $variant->productType !== ProductType::Physical->value) {
            throw new \InvalidArgumentException("Variant {$variantId} is not a tracked physical variant.");
        }

        return DB::transaction(function () use ($variantId, $quantity, $referenceType, $referenceId, $expiresAt): StockReservationData {
            $item = $this->locator->lockOrCreateForVariant($variantId);
            $this->releaseExpiredForLockedItem($item);

            $available = Quantity::sub($item->on_hand_quantity, $item->reserved_quantity);

            if (! $item->allow_backorder && Quantity::compare($quantity, $available) > 0) {
                throw new InsufficientStockException($variantId, $quantity, $available);
            }

            $item->update(['reserved_quantity' => Quantity::add($item->reserved_quantity, $quantity)]);

            $reservation = StockReservation::query()->create([
                'stock_item_id' => $item->id,
                'quantity' => $quantity,
                'state' => StockReservationState::Pending->value,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'expires_at' => $expiresAt,
            ]);

            return new StockReservationData(
                id: (int) $reservation->getKey(),
                variantId: $variantId,
                quantity: $reservation->quantity,
                state: StockReservationState::Pending,
                referenceType: $referenceType,
                referenceId: $referenceId,
                expiresAt: $expiresAt,
            );
        });
    }

    public function confirm(int $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = StockReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->state === StockReservationState::Confirmed->value) {
                return;
            }

            if ($reservation->state !== StockReservationState::Pending->value) {
                throw new InvalidReservationStateException($reservationId, StockReservationState::from($reservation->state), 'confirm');
            }

            $reservation->update(['state' => StockReservationState::Confirmed->value, 'expires_at' => null]);
        });
    }

    public function release(int $reservationId): void
    {
        DB::transaction(function () use ($reservationId): void {
            $reservation = StockReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if (in_array($reservation->state, [
                StockReservationState::Released->value,
                StockReservationState::Expired->value,
                StockReservationState::Fulfilled->value,
            ], true)) {
                return;
            }

            $item = StockItem::query()->lockForUpdate()->findOrFail($reservation->stock_item_id);
            $item->update([
                'reserved_quantity' => $this->clampToZero(Quantity::sub($item->reserved_quantity, $reservation->quantity)),
            ]);
            $reservation->update(['state' => StockReservationState::Released->value]);
        });
    }

    public function fulfill(int $reservationId, ?string $actorId = null): void
    {
        DB::transaction(function () use ($reservationId, $actorId): void {
            $reservation = StockReservation::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->state === StockReservationState::Fulfilled->value) {
                return;
            }

            if (! in_array($reservation->state, [StockReservationState::Pending->value, StockReservationState::Confirmed->value], true)) {
                throw new InvalidReservationStateException($reservationId, StockReservationState::from($reservation->state), 'fulfill');
            }

            $item = StockItem::query()->lockForUpdate()->findOrFail($reservation->stock_item_id);
            $item->update([
                'reserved_quantity' => $this->clampToZero(Quantity::sub($item->reserved_quantity, $reservation->quantity)),
                'on_hand_quantity' => Quantity::sub($item->on_hand_quantity, $reservation->quantity),
            ]);

            StockMovement::query()->create([
                'stock_item_id' => $item->id,
                'type' => StockMovementType::Issue->value,
                'quantity_delta' => '-'.$reservation->quantity,
                'reason' => null,
                'reference_type' => 'stock_reservation',
                'reference_id' => (string) $reservation->getKey(),
                'actor_id' => $actorId,
                'occurred_at' => now(),
            ]);

            $reservation->update(['state' => StockReservationState::Fulfilled->value]);
        });
    }

    public function reconcileExpiredReservations(?int $variantId = null, ?int $limit = null): int
    {
        $itemIds = $variantId === null
            ? StockReservation::query()
                ->where('state', StockReservationState::Pending->value)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->distinct()
                ->when($limit !== null, static fn ($query) => $query->limit(max(1, $limit)))
                ->pluck('stock_item_id')
            : StockItem::query()->where('variant_id', $variantId)->pluck('id');

        $released = 0;

        foreach ($itemIds as $itemId) {
            $released += DB::transaction(function () use ($itemId): int {
                $item = StockItem::query()->lockForUpdate()->find($itemId);

                return $item === null ? 0 : $this->releaseExpiredForLockedItem($item);
            });
        }

        return $released;
    }

    /**
     * Releases this stock item's expired pending reservations. Caller
     * must already hold the item's row lock within an open transaction.
     */
    private function releaseExpiredForLockedItem(StockItem $item): int
    {
        $expired = StockReservation::query()
            ->where('stock_item_id', $item->id)
            ->where('state', StockReservationState::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        if ($expired->isEmpty()) {
            return 0;
        }

        $totalReleased = '0';

        foreach ($expired as $reservation) {
            $totalReleased = Quantity::add($totalReleased, $reservation->quantity);
            $reservation->update(['state' => StockReservationState::Expired->value]);
        }

        $item->update(['reserved_quantity' => $this->clampToZero(Quantity::sub($item->reserved_quantity, $totalReleased))]);

        return $expired->count();
    }

    private function clampToZero(string $value): string
    {
        return Quantity::compare($value, '0') < 0 ? '0' : $value;
    }
}
