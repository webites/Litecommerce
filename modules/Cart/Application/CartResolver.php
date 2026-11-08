<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

use Illuminate\Http\Request;
use Litecommerce\Modules\Cart\Contracts\CartOwnershipResolver;
use Litecommerce\Modules\Cart\Infrastructure\CartLocator;
use Litecommerce\Modules\Cart\Infrastructure\GuestCartCookie;
use Litecommerce\Modules\Cart\Infrastructure\Models\Cart;
use Litecommerce\Modules\Store\Contracts\CurrencyRepository;

/**
 * Resolves the current request's open cart id, creating one on first
 * touch. Deliberately does not lock the row or open a transaction here —
 * cart creation must fully commit before the guest cookie naming it is
 * queued, so mutation services take their own lock by id afterwards
 * rather than nesting inside this lookup.
 */
final class CartResolver implements CartOwnershipResolver
{
    public function __construct(
        private readonly CartLocator $locator,
        private readonly GuestCartCookie $cookie,
        private readonly CurrencyRepository $currencies,
    ) {}

    public function resolveCartId(Request $request): int
    {
        $customerAccountId = $this->authenticatedCustomerId();

        if ($customerAccountId !== null) {
            $cart = $this->locator->findOpenForAccount($customerAccountId);

            if ($cart !== null) {
                return $cart->id;
            }

            return $this->locator->createForAccount($customerAccountId, $this->defaultCurrencyCode())->id;
        }

        $token = $this->cookie->read($request);
        $cart = $token === null ? null : $this->locator->findOpenForGuestToken($token);

        if ($cart !== null) {
            return $cart->id;
        }

        $cart = $this->locator->createForGuest($this->defaultCurrencyCode());
        $this->cookie->queue((string) $cart->guest_token);

        return $cart->id;
    }

    /**
     * Every cart id the current guest/account identity owns, including
     * ones already checked out — for verifying access to a checkout
     * draft, which must keep working after its source cart closed (a
     * confirmed draft's own review/replay requests would otherwise 404,
     * since resolveCartId() deliberately hands out a fresh cart once the
     * old one is checked out).
     *
     * @return list<int>
     */
    public function resolveOwningCartIds(Request $request): array
    {
        $customerAccountId = $this->authenticatedCustomerId();

        if ($customerAccountId !== null) {
            return Cart::query()->where('customer_account_id', $customerAccountId)->pluck('id')->all();
        }

        $token = $this->cookie->read($request);

        if ($token === null) {
            return [];
        }

        return Cart::query()->where('guest_token', $token)->pluck('id')->all();
    }

    public function authenticatedCustomerId(): ?int
    {
        $id = auth('customer')->id();

        return $id === null ? null : (int) $id;
    }

    private function defaultCurrencyCode(): string
    {
        $base = $this->currencies->base();

        return $base === null ? 'PLN' : $base->code;
    }
}
