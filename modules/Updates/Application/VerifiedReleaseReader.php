<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Updates\Contracts\InvalidReleaseManifestException;
use Litecommerce\Modules\Updates\Contracts\PackageSignatureVerifier;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;

/** Reads a release's manifest from the release source and returns it only when the signature is trusted. */
final class VerifiedReleaseReader
{
    public function __construct(
        private readonly ReleaseSource $releases,
        private readonly PackageSignatureVerifier $signatures,
        private readonly ReleaseManifestParser $parser,
    ) {}

    /** @throws UntrustedReleaseException */
    public function read(ReleaseInfo $release): ReleaseManifest
    {
        $manifestJson = $this->releases->readAsset($release->manifestUrl);

        if (! $this->signatures->verify($manifestJson, trim($this->releases->readAsset($release->manifestSignatureUrl)))) {
            throw new UntrustedReleaseException('signature_invalid');
        }

        try {
            $manifest = $this->parser->parse($manifestJson);
        } catch (InvalidReleaseManifestException) {
            throw new UntrustedReleaseException('manifest_invalid');
        }

        if ($manifest->version->compareTo($release->version) !== 0) {
            throw new UntrustedReleaseException('manifest_invalid');
        }

        return $manifest;
    }
}
