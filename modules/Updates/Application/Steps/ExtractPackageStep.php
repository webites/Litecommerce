<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\PackageExtractor;
use Litecommerce\Modules\Updates\Application\ReleaseManifestParser;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdatePaths;
use Litecommerce\Modules\Updates\Application\UpdateProgress;

final class ExtractPackageStep extends BaseUpdateStep
{
    public function __construct(
        private readonly PackageExtractor $extractor,
        private readonly ReleaseManifestParser $parser,
        private readonly UpdatePaths $paths,
    ) {}

    public function key(): string
    {
        return 'extract';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        $manifest = $this->parser->parse((string) file_get_contents($this->paths->workDirectory($state->id).'/manifest.json'));

        $this->extractor->extract($this->paths->archive($state->id), $this->paths->staging($state->id), $manifest);
    }
}
