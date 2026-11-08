<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Updates\Application;

use RuntimeException;

final class UntrustedReleaseException extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct("Release rejected: {$errorCode}");
    }
}
