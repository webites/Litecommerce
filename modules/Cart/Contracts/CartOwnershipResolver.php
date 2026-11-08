<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Contracts;

use Illuminate\Http\Request;

/**
 * The only Contracts-safe way for another module (Storefront) to check
 * which cart ids the current request's guest/customer identity owns —
 * Application\CartResolver itself is private to this module. Implemented
 * directly by CartResolver, so there is exactly one place this logic lives.
 */
interface CartOwnershipResolver
{
    /** @return list<int> every cart id (including already checked-out ones) the current request's identity owns */
    public function resolveOwningCartIds(Request $request): array;
}
