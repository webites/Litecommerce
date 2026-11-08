<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

interface PackageSignatureVerifier
{
    /** True only when one of the trusted keys signed exactly this message; never throws on malformed input. */
    public function verify(string $message, string $signatureBase64): bool;
}
