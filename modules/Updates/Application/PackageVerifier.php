<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Updates\Contracts\InvalidReleaseManifestException;
use Litecommerce\Modules\Updates\Contracts\PackageSignatureVerifier;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use ZipArchive;

/** Checks a downloaded archive before anything is unpacked: signature, manifest, entry names and sizes. */
final class PackageVerifier
{
    public function __construct(
        private readonly PackageSignatureVerifier $signatures,
        private readonly ReleaseManifestParser $parser,
        private readonly int $maxUncompressedBytes,
        private readonly int $maxCompressionRatio,
    ) {}

    /** @throws UpdateStepFailedException */
    public function verify(string $archive, string $expectedVersion): ReleaseManifest
    {
        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new UpdateStepFailedException('archive_unreadable');
        }

        try {
            $manifestJson = $zip->getFromName(BuildReleasePackage::MANIFEST);
            $signature = $zip->getFromName(BuildReleasePackage::SIGNATURE);

            if ($manifestJson === false || $signature === false || ! $this->signatures->verify($manifestJson, trim($signature))) {
                throw new UpdateStepFailedException('signature_invalid');
            }

            try {
                $manifest = $this->parser->parse($manifestJson);
            } catch (InvalidReleaseManifestException) {
                throw new UpdateStepFailedException('manifest_invalid');
            }

            if ($manifest->version->toString() !== $expectedVersion) {
                throw new UpdateStepFailedException('manifest_invalid');
            }

            $this->checkEntries($zip, $manifest);

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    private function checkEntries(ZipArchive $zip, ReleaseManifest $manifest): void
    {
        $allowed = [...array_keys($manifest->files), BuildReleasePackage::MANIFEST, BuildReleasePackage::SIGNATURE];
        $seen = [];
        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false || str_ends_with($stat['name'], '/')) {
                continue;
            }

            if (! in_array($stat['name'], $allowed, true)) {
                throw new UpdateStepFailedException('unexpected_entry', "The package contains an unlisted entry: {$stat['name']}");
            }

            $total += (int) $stat['size'];
            $compressed = max(1, (int) $stat['comp_size']);

            if ($total > $this->maxUncompressedBytes || ((int) $stat['size'] > 1048576 && $this->maxCompressionRatio < $stat['size'] / $compressed)) {
                throw new UpdateStepFailedException('archive_too_large');
            }

            $seen[$stat['name']] = true;
        }

        if (array_diff(array_keys($manifest->files), array_keys($seen)) !== []) {
            throw new UpdateStepFailedException('files_missing');
        }
    }
}
