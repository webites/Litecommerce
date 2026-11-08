<?php

declare(strict_types=1);

namespace Tests\Unit\Packaging;

use App\Support\Packaging\PackageSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class PackageSignatureVerifierTest extends TestCase
{
    private string $zip;

    protected function setUp(): void
    {
        $this->zip = tempnam(sys_get_temp_dir(), 'lc-sig-');
        file_put_contents($this->zip, 'package bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->zip);
    }

    /** @return array{0: string, 1: string} [base64 public key, base64 signature] */
    private function sign(string $path): array
    {
        $pair = sodium_crypto_sign_keypair();
        $signature = sodium_crypto_sign_detached('litecommerce-package-v1:'.hash_file('sha256', $path), sodium_crypto_sign_secretkey($pair));

        return [base64_encode(sodium_crypto_sign_publickey($pair)), base64_encode($signature)];
    }

    public function test_a_package_signed_by_a_trusted_key_is_accepted_and_the_key_is_named(): void
    {
        [$public, $signature] = $this->sign($this->zip);

        $this->assertSame('catalog-2026', (new PackageSignatureVerifier(['catalog-2026' => $public]))->verify($this->zip, $signature));
    }

    public function test_a_modified_package_is_rejected(): void
    {
        [$public, $signature] = $this->sign($this->zip);
        file_put_contents($this->zip, 'package bytes plus a backdoor');

        $this->assertNull((new PackageSignatureVerifier(['catalog-2026' => $public]))->verify($this->zip, $signature));
    }

    public function test_an_untrusted_or_revoked_key_and_garbage_signatures_are_rejected(): void
    {
        [, $signature] = $this->sign($this->zip);
        [$otherPublic] = $this->sign($this->zip);

        $this->assertNull((new PackageSignatureVerifier(['other' => $otherPublic]))->verify($this->zip, $signature));
        $this->assertNull((new PackageSignatureVerifier([]))->verify($this->zip, $signature));
        $this->assertNull((new PackageSignatureVerifier(['other' => $otherPublic]))->verify($this->zip, 'not-base64!!'));
    }
}
