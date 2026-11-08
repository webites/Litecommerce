<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

/** A lifecycle transition (install/enable/disable/uninstall) was refused by a rule — incompatibility, an unmet or blocking dependency, or wrong current state. */
final class ExtensionStateException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($message);
    }
}
