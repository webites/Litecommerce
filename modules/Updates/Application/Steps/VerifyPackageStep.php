<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\PackageVerifier;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use ZipArchive;

final class VerifyPackageStep extends BaseUpdateStep
{
    public function __construct(private readonly PackageVerifier $verifier, private readonly UpdatePaths $paths) {}

    public function key(): string
    {
        return 'verify';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        $archive = $this->paths->archive($state->id);
        $this->verifier->verify($archive, $state->targetVersion);

        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::RDONLY);
        file_put_contents($this->paths->workDirectory($state->id).'/manifest.json', (string) $zip->getFromName('manifest.json'));
        $zip->close();
    }
}
