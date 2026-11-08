<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Infrastructure;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\ReleaseSourceException;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;

final class GitHubReleaseSource implements ReleaseSource
{
    private const MAX_ASSET_BYTES = 2_000_000;

    private const ASSET_HOSTS = ['github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];

    public function __construct(private readonly string $repository) {}

    public function stableReleases(): array
    {
        if (preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $this->repository) !== 1) {
            throw new ReleaseSourceException('The release repository is not configured.');
        }

        try {
            $response = $this->client()->get("https://api.github.com/repos/{$this->repository}/releases", ['per_page' => 30]);
        } catch (ConnectionException $exception) {
            throw new ReleaseSourceException('GitHub could not be reached.', 0, $exception);
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new ReleaseSourceException("GitHub answered with HTTP {$response->status()}.");
        }

        $releases = [];
        foreach ($response->json() as $item) {
            $release = is_array($item) ? $this->toRelease($item) : null;
            if ($release !== null) {
                $releases[] = $release;
            }
        }

        return $releases;
    }

    public function download(string $url, string $destination, int $maxBytes): void
    {
        $this->assertGitHubAsset($url);

        try {
            $response = $this->client()->timeout(300)->sink($destination)->get($url);
        } catch (ConnectionException $exception) {
            @unlink($destination);

            throw new ReleaseSourceException('GitHub could not be reached.', 0, $exception);
        }

        if (! $response->successful() || (int) @filesize($destination) > $maxBytes) {
            @unlink($destination);

            throw new ReleaseSourceException("The release archive could not be downloaded (HTTP {$response->status()}).");
        }
    }

    public function readAsset(string $url): string
    {
        $this->assertGitHubAsset($url);

        try {
            $response = $this->client()->get($url);
        } catch (ConnectionException $exception) {
            throw new ReleaseSourceException('GitHub could not be reached.', 0, $exception);
        }

        if (! $response->successful() || strlen($response->body()) > self::MAX_ASSET_BYTES) {
            throw new ReleaseSourceException("The asset could not be downloaded (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    private function assertGitHubAsset(string $url): void
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array(parse_url($url, PHP_URL_HOST), self::ASSET_HOSTS, true)) {
            throw new ReleaseSourceException('The asset is not hosted on GitHub.');
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout(10)->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'Litecommerce-Updater']);
    }

    /** @param array<string, mixed> $item */
    private function toRelease(array $item): ?ReleaseInfo
    {
        $version = SemanticVersion::tryParse((string) ($item['tag_name'] ?? ''));

        if ($version === null || ! $version->isStable() || ($item['draft'] ?? false) || ($item['prerelease'] ?? false)) {
            return null;
        }

        $assets = [];
        foreach (is_array($item['assets'] ?? null) ? $item['assets'] : [] as $asset) {
            if (is_array($asset) && isset($asset['name'], $asset['browser_download_url'])) {
                $assets[(string) $asset['name']] = (string) $asset['browser_download_url'];
            }
        }

        $archive = "litecommerce-{$version->toString()}.zip";
        $required = [$archive, $archive.'.sha256', 'manifest.json', 'manifest.json.sig'];

        if (array_diff($required, array_keys($assets)) !== []) {
            return null;
        }

        return new ReleaseInfo(
            $version, (string) $item['tag_name'], $assets[$archive], $assets[$archive.'.sha256'],
            $assets['manifest.json'], $assets['manifest.json.sig'], (string) ($item['html_url'] ?? ''),
            isset($item['published_at']) ? (string) $item['published_at'] : null,
        );
    }
}
