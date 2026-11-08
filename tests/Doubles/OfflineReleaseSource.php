<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\ReleaseSourceException;

final class OfflineReleaseSource implements ReleaseSource
{
    public function stableReleases(): array
    {
        throw new ReleaseSourceException('Release source is offline in tests.');
    }

    public function readAsset(string $url): string
    {
        throw new ReleaseSourceException('Release source is offline in tests.');
    }

    public function download(string $url, string $destination, int $maxBytes): void
    {
        throw new ReleaseSourceException('Release source is offline in tests.');
    }
}
