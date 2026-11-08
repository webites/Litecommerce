<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use ZipArchive;

/** Unpacks a verified archive into a staging directory and checks every file against the signed manifest. */
final class PackageExtractor
{
    /** @throws UpdateStepFailedException */
    public function extract(string $archive, string $staging, ReleaseManifest $manifest): void
    {
        self::removeDirectory($staging);

        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new UpdateStepFailedException('archive_unreadable');
        }

        try {
            foreach ([...$manifest->files, BuildReleasePackage::MANIFEST => null] as $path => $expectedHash) {
                $this->extractEntry($zip, $staging, (string) $path, $expectedHash);
            }
        } finally {
            $zip->close();
        }
    }

    public static function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }

    private function extractEntry(ZipArchive $zip, string $staging, string $path, ?string $expectedHash): void
    {
        $source = $zip->getStream($path);
        $destination = $staging.'/'.$path;

        if ($source === false) {
            throw new UpdateStepFailedException('files_missing');
        }

        if (! is_dir(dirname($destination)) && ! @mkdir(dirname($destination), 0775, true) && ! is_dir(dirname($destination))) {
            throw new UpdateStepFailedException('staging_unwritable');
        }

        $target = fopen($destination, 'wb');
        $hash = hash_init('sha256');
        while (! feof($source)) {
            $chunk = (string) fread($source, 1048576);
            hash_update($hash, $chunk);
            fwrite($target, $chunk);
        }
        fclose($source);
        fclose($target);

        if ($expectedHash !== null && ! hash_equals($expectedHash, hash_final($hash))) {
            throw new UpdateStepFailedException('checksum_mismatch', "Checksum mismatch: {$path}");
        }
    }
}
