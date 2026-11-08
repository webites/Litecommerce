<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use FilesystemIterator;
use InvalidArgumentException;
use Litecommerce\Modules\Installer\Contracts\EnvironmentRequirements;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Builds the release ZIP: every shipped file plus manifest.json and its detached Ed25519 signature
 * (manifest.json.sig); both are also written next to the archive so an update check can read them without
 * downloading the archive. Installation and updates use the same archive.
 */
final class BuildReleasePackage
{
    public const MANIFEST = 'manifest.json';

    public const SIGNATURE = 'manifest.json.sig';

    /** @param list<string> $excluded paths relative to the source root; a directory excludes everything below it */
    public function __construct(
        private readonly EnvironmentRequirements $requirements,
        private readonly array $excluded,
    ) {}

    /** @return array{archive: string, checksum: string, manifest: string} */
    public function build(string $sourceDirectory, string $outputDirectory, string $version, string $secretKeyBase64, ?string $upgradeableFrom = null): array
    {
        $parsed = SemanticVersion::parse($version);
        if (! $parsed->isStable()) {
            throw new InvalidArgumentException('Only stable versions can be released.');
        }

        $secretKey = base64_decode($secretKeyBase64, true);
        if ($secretKey === false || strlen($secretKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException('The signing key is not a valid base64 Ed25519 secret key.');
        }

        $source = rtrim($sourceDirectory, '/');
        $files = $this->collect($source);

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException("Cannot create {$outputDirectory}.");
        }

        $manifest = json_encode([
            'format' => ReleaseManifest::FORMAT,
            'version' => $parsed->toString(),
            'min_php' => $this->requirements->minimumPhpVersion(),
            'php_extensions' => $this->requirements->requiredExtensions(),
            'upgrade_from' => $upgradeableFrom,
            'built_at' => gmdate('c'),
            'files' => $files,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $archivePath = rtrim($outputDirectory, '/')."/litecommerce-{$parsed->toString()}.zip";
        $signature = base64_encode(sodium_crypto_sign_detached($manifest, $secretKey));
        $this->write($archivePath, $source, array_keys($files), $manifest, $signature);
        file_put_contents(rtrim($outputDirectory, '/').'/'.self::MANIFEST, $manifest);
        file_put_contents(rtrim($outputDirectory, '/').'/'.self::SIGNATURE, $signature);
        file_put_contents($archivePath.'.sha256', hash_file('sha256', $archivePath).'  '.basename($archivePath)."\n");

        return ['archive' => $archivePath, 'checksum' => $archivePath.'.sha256', 'manifest' => $manifest];
    }

    /** @return array<string, string> */
    private function collect(string $source): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file): bool => ! $this->isExcluded(substr($file->getPathname(), strlen($source) + 1)),
        ));

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && ! $file->isLink()) {
                $files[substr($file->getPathname(), strlen($source) + 1)] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($files);

        return $files;
    }

    private function isExcluded(string $relativePath): bool
    {
        foreach ($this->excluded as $excluded) {
            if ($relativePath === $excluded || str_starts_with($relativePath, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $paths */
    private function write(string $archivePath, string $source, array $paths, string $manifest, string $signature): void
    {
        @unlink($archivePath);
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException("Cannot create {$archivePath}.");
        }

        foreach ($paths as $path) {
            $zip->addFile("{$source}/{$path}", $path);
        }
        $zip->addFromString(self::MANIFEST, $manifest);
        $zip->addFromString(self::SIGNATURE, $signature);

        if (! $zip->close()) {
            throw new RuntimeException("Cannot write {$archivePath}.");
        }
    }
}
