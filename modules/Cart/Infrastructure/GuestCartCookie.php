<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Infrastructure;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * The guest cart's cookie carries only an opaque token, never the cart's
 * numeric id — EncryptCookies (default in the 'web' middleware group)
 * already makes it unguessable, this just avoids depending on that being
 * the only defense.
 */
final class GuestCartCookie
{
    public function read(Request $request): ?string
    {
        $token = $request->cookie($this->name());

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function queue(string $token): void
    {
        Cookie::queue($this->cookie($token));
    }

    public function forget(): void
    {
        Cookie::queue(Cookie::forget($this->name()));
    }

    private function cookie(string $token): SymfonyCookie
    {
        return Cookie::make(
            name: $this->name(),
            value: $token,
            minutes: (int) config('cart.guest_cookie_ttl_days') * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        );
    }

    private function name(): string
    {
        return (string) config('cart.guest_cookie_name');
    }
}
