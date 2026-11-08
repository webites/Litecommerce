<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * Laravel's JSON test helpers send no cookies at all unless
 * withCredentials() is on, and never carry a Set-Cookie from one response
 * into the next call automatically (unlike a real browser) — needed here
 * because both the guest cart cookie and the session cookie (customer
 * login) must survive across requests within a single test. Cookie
 * values from a real response are already encrypted ciphertext, so they
 * go back in via withUnencryptedCookie() (sent as-is) rather than
 * withCookie() (which would encrypt them a second time).
 */
trait CarriesTestCookies
{
    protected function carryCookiesFrom(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), (string) $cookie->getValue());
        }
    }
}
