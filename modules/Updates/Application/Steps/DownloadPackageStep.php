<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Litecommerce\Modules\Updates\Contracts\ReleaseSourceException;

final class DownloadPackageStep extends BaseUpdateStep
{
    public function __construct(
        private readonly ReleaseSource $releases,
        private readonly UpdatePaths $paths,
        private readonly int $maxArchiveBytes,
    ) {}

    public function key(): string
    {
        return 'download';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        $release = ReleaseInfo::fromArray($state->release);
        $archive = $this->paths->archive($state->id);

        try {
            $expected = strtolower(strtok(trim($this->releases->readAsset($release->checksumUrl)), " \t\n") ?: '');

            if (preg_match('/^[0-9a-f]{64}$/', $expected) !== 1) {
                throw new UpdateStepFailedException('checksum_invalid');
            }

            if (! is_file($archive) || hash_file('sha256', $archive) !== $expected) {
                if (! is_dir(dirname($archive)) && ! @mkdir(dirname($archive), 0775, true) && ! is_dir(dirname($archive))) {
                    throw new UpdateStepFailedException('staging_unwritable');
                }

                $this->releases->download($release->archiveUrl, $archive, $this->maxArchiveBytes);
            }
        } catch (ReleaseSourceException) {
            throw new UpdateStepFailedException('source_unreachable');
        }

        if (hash_file('sha256', $archive) !== $expected) {
            @unlink($archive);

            throw new UpdateStepFailedException('checksum_mismatch', 'The downloaded archive does not match its published checksum.');
        }
    }
}
