<?php

declare(strict_types=1);

namespace App\Support\Packaging;

/**
 * Verifies a detached Ed25519 signature over the package's SHA-256 digest against the configured trusted keys.
 * Not required for a locally uploaded ZIP (SHA-256 plus acknowledgement is the trust model there); meant for packages from an API catalog.
 */
final class PackageSignatureVerifier
{
    private const DOMAIN = 'litecommerce-package-v1:';

    /** @param array<string, string> $trustedKeys key id => base64 Ed25519 public key */
    public function __construct(private readonly array $trustedKeys) {}

    /** Returns the id of the trusted key that signed the package, or null. */
    public function verify(string $zipPath, string $signatureBase64): ?string
    {
        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \RuntimeException('The sodium extension is required to verify package signatures.');
        }

        $digest = hash_file('sha256', $zipPath);
        $signature = base64_decode($signatureBase64, true);
        if ($digest === false || $signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        foreach ($this->trustedKeys as $keyId => $publicKey) {
            $key = base64_decode($publicKey, true);
            if ($key !== false && strlen($key) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES && sodium_crypto_sign_verify_detached($signature, self::DOMAIN.$digest, $key)) {
                return (string) $keyId;
            }
        }

        return null;
    }
}
