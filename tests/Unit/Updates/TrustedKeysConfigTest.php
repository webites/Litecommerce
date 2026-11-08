<?php

declare(strict_types=1);

namespace Tests\Unit\Updates;

use PHPUnit\Framework\TestCase;

final class TrustedKeysConfigTest extends TestCase
{
    public function test_every_built_in_key_is_a_valid_ed25519_public_key(): void
    {
        $keys = (require dirname(__DIR__, 3).'/config/updates.php')['trusted_public_keys'];

        $this->assertNotEmpty($keys);

        foreach ($keys as $key) {
            $decoded = base64_decode($key, true);
            $this->assertNotFalse($decoded);
            $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($decoded));
        }
    }
}
