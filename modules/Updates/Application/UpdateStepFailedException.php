<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use RuntimeException;

final class UpdateStepFailedException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "Update step failed: {$errorCode}");
    }
}
