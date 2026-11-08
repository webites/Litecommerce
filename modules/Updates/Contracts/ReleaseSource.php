<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Contracts;

interface ReleaseSource
{
    /**
     * Stable, published releases that carry every asset an update needs; drafts, pre-releases and tags that are not semver are skipped.
     *
     * @return list<ReleaseInfo>
     *
     * @throws ReleaseSourceException
     */
    public function stableReleases(): array;

    /**
     * Small text asset (manifest, signature) of a release.
     *
     * @throws ReleaseSourceException
     */
    public function readAsset(string $url): string;

    /**
     * Streams a release asset to a local file; the file is removed again when the download fails or exceeds the limit.
     *
     * @throws ReleaseSourceException
     */
    public function download(string $url, string $destination, int $maxBytes): void;
}
