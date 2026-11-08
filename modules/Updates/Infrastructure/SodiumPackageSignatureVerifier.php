<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Infrastructure;

use Litecommerce\Modules\Updates\Contracts\PackageSignatureVerifier;
use Throwable;

final class SodiumPackageSignatureVerifier implements PackageSignatureVerifier
{
    /** @param list<string> $trustedPublicKeys base64 Ed25519 public keys */
    public function __construct(private readonly array $trustedPublicKeys) {}

    public function verify(string $message, string $signatureBase64): bool
    {
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        foreach ($this->trustedPublicKeys as $encodedKey) {
            $key = base64_decode($encodedKey, true);

            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                continue;
            }

            try {
                if (sodium_crypto_sign_verify_detached($signature, $message, $key)) {
                    return true;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }
}
