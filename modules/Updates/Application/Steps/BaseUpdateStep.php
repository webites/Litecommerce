<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Litecommerce\Modules\Updates\Application\UpdateStep;

abstract class BaseUpdateStep implements UpdateStep
{
    public function isIrreversible(): bool
    {
        return false;
    }

    public function endsRequest(): bool
    {
        return false;
    }
}
