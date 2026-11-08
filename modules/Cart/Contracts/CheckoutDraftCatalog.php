<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

interface CheckoutDraftCatalog
{
    /**
     * Returns the confirmed draft's frozen snapshot, or null when the id
     * is unknown or the draft never reached the confirmed state.
     */
    public function findConfirmed(string $draftId): ?ConfirmedCheckoutDraftData;

    /**
     * Same as findConfirmed(), gated additionally by ownership — for a
     * caller (the storefront's order confirmation page) with no prior
     * ownership check of its own, unlike findConfirmed()'s trusted-caller
     * convention. $ownedCartIds comes from
     * Cart\Contracts\CartOwnershipResolver::resolveOwningCartIds().
     *
     * @param  list<int>  $ownedCartIds
     */
    public function findConfirmedOwnedBy(string $draftId, array $ownedCartIds): ?ConfirmedCheckoutDraftData;
}
