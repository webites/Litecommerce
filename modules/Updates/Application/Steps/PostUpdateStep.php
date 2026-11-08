<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application\Steps;

use Illuminate\Support\Facades\Artisan;
use Litecommerce\Modules\Updates\Application\UpdateOperationState;
use Litecommerce\Modules\Updates\Application\UpdateProgress;
use Litecommerce\Modules\Updates\Application\UpdateStepFailedException;
use Throwable;

/** Drops every cache built from the previous code so the next request runs the new release. */
final class PostUpdateStep extends BaseUpdateStep
{
    public function key(): string
    {
        return 'post_update';
    }

    public function run(UpdateOperationState $state, UpdateProgress $progress): void
    {
        try {
            Artisan::call('optimize:clear');
        } catch (Throwable $exception) {
            throw new UpdateStepFailedException('post_update_failed', $exception->getMessage());
        }

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
}
