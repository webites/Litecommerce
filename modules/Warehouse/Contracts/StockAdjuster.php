<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Warehouse\Contracts;

interface StockAdjuster
{
    /**
     * Creates the variant's stock item on first use (0 on hand) if it
     * doesn't exist yet.
     */
    public function setAllowBackorder(int $variantId, bool $allowed): void;

    /**
     * Adds quantity via a "receipt" movement (e.g. a supplier delivery),
     * creating the stock item on first use.
     */
    public function receive(int $variantId, string $quantity, ?string $reason, ?string $actorId): void;

    /**
     * Adds quantity back via a "return" movement — goods physically
     * returned to stock, independent of any reservation.
     */
    public function returnToStock(int $variantId, string $quantity, ?string $reason, ?string $actorId): void;

    /**
     * Sets on-hand quantity to an absolute value. Never a silent
     * overwrite: records a "correction" movement equal to the difference,
     * per docs/knowledge-base/06-fulfillment.md ("Ręczne ustawienie stanu
     * tworzy ruch różnicy"). No-op (no movement recorded) when the new
     * value equals the current one.
     */
    public function correctOnHand(int $variantId, string $newOnHandQuantity, ?string $reason, ?string $actorId): void;
}
