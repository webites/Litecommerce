<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use JsonException;
use Litecommerce\Modules\Updates\Contracts\InvalidReleaseManifestException;
use Litecommerce\Modules\Updates\Contracts\ReleaseManifest;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;

final class ReleaseManifestParser
{
    public function parse(string $json): ReleaseManifest
    {
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidReleaseManifestException('The manifest is not valid JSON.', 0, $exception);
        }

        if (! is_array($data) || ($data['format'] ?? null) !== ReleaseManifest::FORMAT) {
            throw new InvalidReleaseManifestException('Unsupported manifest format.');
        }

        $version = SemanticVersion::tryParse((string) ($data['version'] ?? ''));
        if ($version === null || ! $version->isStable()) {
            throw new InvalidReleaseManifestException('The manifest version must be a stable semantic version.');
        }

        $upgradeFrom = $data['upgrade_from'] ?? null;
        $upgradeableFrom = $upgradeFrom === null ? null : SemanticVersion::tryParse((string) $upgradeFrom);
        if ($upgradeFrom !== null && $upgradeableFrom === null) {
            throw new InvalidReleaseManifestException('The upgrade_from value is not a semantic version.');
        }

        $minimumPhp = $data['min_php'] ?? null;
        if (! is_string($minimumPhp) || preg_match('/^\d+\.\d+(\.\d+)?$/', $minimumPhp) !== 1) {
            throw new InvalidReleaseManifestException('The min_php value is missing or malformed.');
        }

        $extensions = $data['php_extensions'] ?? null;
        if (! is_array($extensions) || ! array_is_list($extensions) || array_filter($extensions, static fn ($e): bool => ! is_string($e) || preg_match('/^[a-z0-9_]+$/i', $e) !== 1) !== []) {
            throw new InvalidReleaseManifestException('The php_extensions value must be a list of extension names.');
        }

        $builtAt = $data['built_at'] ?? null;
        if (! is_string($builtAt) || $builtAt === '') {
            throw new InvalidReleaseManifestException('The built_at value is missing.');
        }

        return new ReleaseManifest($version, $minimumPhp, $extensions, $upgradeableFrom, $builtAt, $this->files($data['files'] ?? null));
    }

    /** @return array<string, string> */
    private function files(mixed $files): array
    {
        if (! is_array($files) || $files === []) {
            throw new InvalidReleaseManifestException('The manifest lists no files.');
        }

        foreach ($files as $path => $hash) {
            if (! is_string($path) || ! $this->isSafePath($path) || ! is_string($hash) || preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
                throw new InvalidReleaseManifestException('The manifest contains an unsafe path or a malformed checksum.');
            }
        }

        return $files;
    }

    private function isSafePath(string $path): bool
    {
        return $path !== ''
            && $path[0] !== '/'
            && ! str_contains($path, '\\')
            && ! str_contains($path, "\0")
            && array_intersect(explode('/', $path), ['', '.', '..']) === [];
    }
}
