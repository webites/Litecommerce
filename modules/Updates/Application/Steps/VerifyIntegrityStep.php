<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\ReleaseManifestParser;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;

/** Compares every installed release file with the signed manifest before the store leaves maintenance. */
final class VerifyIntegrityStep extends BaseUpdateStep
{
    public function __construct(private readonly ReleaseManifestParser $parser, private readonly UpdatePaths $paths) {}

    public function key(): string
    {
        return 'integrity';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        $manifest = $this->parser->parse((string) file_get_contents($this->paths->workDirectory($state->id).'/manifest.json'));
        $broken = [];

        foreach ($manifest->files as $path => $hash) {
            if ($this->paths->isProtected($path)) {
                continue;
            }

            $file = $this->paths->root.'/'.$path;

            if (! is_file($file) || hash_file('sha256', $file) !== $hash) {
                $broken[] = $path;
            }
        }

        if ($broken !== []) {
            throw new UpdateStepFailedException('integrity_failed', 'Files differ from the release manifest: '.implode(', ', array_slice($broken, 0, 10)).(count($broken) > 10 ? ' and '.(count($broken) - 10).' more' : ''));
        }
    }
}
