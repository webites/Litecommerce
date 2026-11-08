<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Litecommerce\Modules\Cart\Infrastructure\Models\CheckoutDraft;
use Litecommerce\Modules\Orders\Contracts\PlacedOrderData;

final readonly class ConfirmResult
{
    private function __construct(
        public string $outcome,
        public ?CheckoutDraft $draft,
        public ?PricedCart $priced,
        public ?string $currentHash,
        public ?PlacedOrderData $order,
    ) {}

    public static function confirmed(CheckoutDraft $draft, PlacedOrderData $order): self
    {
        return new self('confirmed', $draft, null, null, $order);
    }

    /** Replaying an already-confirmed draft's hash is a no-op, not an error. */
    public static function alreadyConfirmed(CheckoutDraft $draft, ?PlacedOrderData $order): self
    {
        return new self('already_confirmed', $draft, null, null, $order);
    }

    public static function hashMismatch(PricedCart $priced, string $currentHash): self
    {
        return new self('hash_mismatch', null, $priced, $currentHash, null);
    }
}
