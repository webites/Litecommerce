<?php

declare(strict_types=1);

namespace App\Support\Packaging;

use ZipArchive;

/**
 * Structural safety checks and bounded extraction for an uploaded ZIP,
 * run before any package code is loaded. Deliberately not a security
 * boundary against a determined malicious author (see "Kod PHP jest
 * zaufanym kodem" in docs/knowledge-base/08-extensions.md): it stops
 * path traversal, symlinks, zip bombs and malformed archives, nothing more.
 *
 * Shared by modules/Extensions and modules/Themes, which install ZIP
 * packages with the same manifest.json-at-root shape. It lives outside
 * modules/ because neither module may import the other's Domain layer.
 */
final class ZipPackageValidator
{
    private const UNIX_SYMLINK_MODE = 0xA000;

    private const MAX_PATH_LENGTH = 255;

    private const MAX_PATH_DEPTH = 20;

    private const RATIO_CHECK_MIN_BYTES = 1048576;

    private const COPY_CHUNK_BYTES = 65536;

    public function __construct(
        private readonly int $maxSizeBytes,
        private readonly int $maxFileCount,
        private readonly int $maxCompressionRatio = 100,
        private readonly int $maxManifestBytes = 1048576,
    ) {}

    /** @return string the raw contents of manifest.json */
    public function validate(ZipArchive $zip): string
    {
        $entryCount = $zip->numFiles;
        if ($entryCount > $this->maxFileCount) {
            throw new PackageValidationException('package_too_many_files', "The package contains {$entryCount} files, the limit is {$this->maxFileCount}.", ['count' => (string) $entryCount, 'limit' => (string) $this->maxFileCount]);
        }

        $totalSize = 0;
        $manifestIndex = null;
        $seen = [];

        for ($i = 0; $i < $entryCount; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                throw new PackageValidationException('package_corrupt', 'Could not read the package contents.');
            }

            $name = $stat['name'];
            $this->assertSafePath($name);

            $key = strtolower(rtrim($name, '/'));
            if (isset($seen[$key])) {
                throw new PackageValidationException('package_duplicate_entry', "The package contains a duplicate entry (\"{$name}\").", ['name' => $name]);
            }
            $seen[$key] = true;

            if ($zip->getExternalAttributesIndex($i, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
                $unixMode = ($attributes >> 16) & 0xF000;
                if ($unixMode === self::UNIX_SYMLINK_MODE) {
                    throw new PackageValidationException('package_contains_symlink', "The package contains a symbolic link (\"{$name}\"), which is not allowed.", ['name' => $name]);
                }
            }

            $size = (int) $stat['size'];
            $totalSize += $size;
            if ($totalSize > $this->maxSizeBytes) {
                throw $this->tooLarge();
            }

            if ($size >= self::RATIO_CHECK_MIN_BYTES && intdiv($size, max(1, (int) $stat['comp_size'])) > $this->maxCompressionRatio) {
                throw new PackageValidationException('package_suspicious_compression', "The package entry \"{$name}\" has a suspiciously high compression ratio.", ['name' => $name]);
            }

            if ($name === 'manifest.json') {
                if ($size > $this->maxManifestBytes) {
                    throw new PackageValidationException('package_manifest_too_large', 'The manifest.json file is too large.');
                }
                $manifestIndex = $i;
            }
        }

        if ($manifestIndex === null) {
            throw new PackageValidationException('package_missing_manifest', 'The package does not contain a manifest.json file at its root.');
        }

        $manifest = $this->readBounded($zip, $manifestIndex, $this->maxManifestBytes);
        if ($manifest === null) {
            throw new PackageValidationException('package_manifest_too_large', 'The manifest.json file is too large.');
        }

        return $manifest;
    }

    /**
     * Extracts a validated archive, counting the bytes that are actually
     * produced rather than trusting sizes declared in the archive headers.
     * On any failure the partially written directory is removed.
     */
    public function extract(ZipArchive $zip, string $targetPath): void
    {
        $root = rtrim($targetPath, '/');
        if ($root === '') {
            throw new PackageValidationException('package_extract_failed', 'Could not extract the package.');
        }
        $total = 0;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    throw new PackageValidationException('package_corrupt', 'Could not read the package contents.');
                }

                $name = $stat['name'];
                $destination = $root.'/'.rtrim($name, '/');
                if (str_ends_with($name, '/')) {
                    if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
                        throw new PackageValidationException('package_extract_failed', 'Could not extract the package.');
                    }

                    continue;
                }

                $directory = dirname($destination);
                if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    throw new PackageValidationException('package_extract_failed', 'Could not extract the package.');
                }

                $total += $this->copyEntry($zip, $i, $destination, (int) $stat['size'], $this->maxSizeBytes - $total);
            }
        } catch (\Throwable $exception) {
            $this->removeDirectory($root);

            throw $exception;
        }
    }

    private function copyEntry(ZipArchive $zip, int $index, string $destination, int $declaredSize, int $remainingBudget): int
    {
        $source = $zip->getStreamIndex($index);
        $target = $source === false ? false : fopen($destination, 'wb');
        if ($source === false || $target === false) {
            throw new PackageValidationException('package_extract_failed', 'Could not extract the package.');
        }

        $written = 0;
        try {
            while (! feof($source)) {
                $chunk = fread($source, self::COPY_CHUNK_BYTES);
                if ($chunk === false) {
                    throw new PackageValidationException('package_corrupt', 'Could not read the package contents.');
                }
                $written += strlen($chunk);
                if ($written > $declaredSize || $written > $remainingBudget) {
                    throw $written > $remainingBudget ? $this->tooLarge() : new PackageValidationException('package_corrupt', 'A package entry is larger than declared.');
                }
                if (fwrite($target, $chunk) === false) {
                    throw new PackageValidationException('package_extract_failed', 'Could not extract the package.');
                }
            }
        } finally {
            fclose($source);
            fclose($target);
        }

        chmod($destination, 0644);

        return $written;
    }

    private function readBounded(ZipArchive $zip, int $index, int $limit): ?string
    {
        $stream = $zip->getStreamIndex($index);
        if ($stream === false) {
            throw new PackageValidationException('package_corrupt', 'Could not read the package contents.');
        }
        $contents = stream_get_contents($stream, $limit + 1);
        fclose($stream);

        return $contents === false || strlen($contents) > $limit ? null : $contents;
    }

    private function tooLarge(): PackageValidationException
    {
        $limitMb = (int) round($this->maxSizeBytes / 1024 / 1024);

        return new PackageValidationException('package_too_large', "The unpacked package exceeds the {$limitMb} MB limit.", ['limit_mb' => (string) $limitMb]);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    private function assertSafePath(string $name): void
    {
        if ($name === '' || str_starts_with($name, '/') || str_contains($name, '\\') || str_contains($name, "\0") || preg_match('/^[A-Za-z]:/', $name) === 1 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new PackageValidationException('package_unsafe_path', "The package contains a disallowed path (\"{$name}\").", ['name' => $name]);
        }

        if (strlen($name) > self::MAX_PATH_LENGTH || substr_count($name, '/') > self::MAX_PATH_DEPTH) {
            throw new PackageValidationException('package_unsafe_path', "The package contains a disallowed path (\"{$name}\").", ['name' => $name]);
        }

        foreach (explode('/', $name) as $segment) {
            if ($segment === '..') {
                throw new PackageValidationException('package_path_traversal', "The package contains an attempt to escape its directory (\"{$name}\").", ['name' => $name]);
            }
        }
    }
}
