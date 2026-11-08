<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

use Illuminate\Http\Request;

/**
 * Called by Identity's customer registration/login controllers right
 * after a successful auth, per "Po logowaniu łączymy" in
 * docs/knowledge-base/05-orders.md. Identity depends on this Contract the
 * same way Warehouse depends on Catalog's — the dependency runs the
 * other direction from most of the module list because the merge only
 * makes sense at the moment of login, which Identity owns. Takes the
 * Request rather than a token so the guest cart cookie's name/format
 * stays Cart's own concern — Identity never reads it directly.
 */
interface CartMerger
{
    /**
     * Merges the request's guest cart (if any) into the customer
     * account's cart, summing shared variant lines and clamping each to
     * the variant's quantity bounds, then clears the guest cookie. No-op,
     * returning CartMergeReport::nothingToMerge(), when there is no
     * guest cookie or no matching open guest cart.
     */
    public function mergeGuestCartIntoAccount(int $customerAccountId, Request $request): CartMergeReport;
}
