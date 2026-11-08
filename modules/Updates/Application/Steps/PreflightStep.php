<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\CompatibilityChecker;
use Litecommerce\Modules\Updates\Application\UntrustedReleaseException;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;
use Litecommerce\Modules\Updates\Application\VerifiedReleaseReader;
use Litecommerce\Modules\Updates\Contracts\ReleaseInfo;
use Litecommerce\Modules\Updates\Contracts\ReleaseSourceException;
use Litecommerce\Modules\Updates\Contracts\SemanticVersion;

final class PreflightStep extends BaseUpdateStep
{
    public function __construct(
        private readonly VerifiedReleaseReader $reader,
        private readonly CompatibilityChecker $compatibility,
        private readonly string $currentVersion,
    ) {}

    public function key(): string
    {
        return 'preflight';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        try {
            $manifest = $this->reader->read(ReleaseInfo::fromArray($state->release));
        } catch (UntrustedReleaseException $exception) {
            throw new UpdateStepFailedException($exception->errorCode);
        } catch (ReleaseSourceException) {
            throw new UpdateStepFailedException('source_unreachable');
        }

        $problems = $this->compatibility->check($manifest, SemanticVersion::parse($this->currentVersion))->problems;

        if ($problems !== []) {
            throw new UpdateStepFailedException('incompatible', 'The release is not compatible: '.implode(', ', array_column($problems, 'code')));
        }
    }
}
