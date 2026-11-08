<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

/**
 * A lifecycle transition (activate/deactivate/delete) was refused by a rule —
 * incompatibility, an unmet plugin dependency, or wrong current state.
 * $message is a plain English fallback; Presentation re-translates via
 * trans("themes::errors.{$errorCode}", $params).
 */
final class ThemeStateException extends \RuntimeException
{
    /** @param array<string, string> $params */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $params = [])
    {
        parent::__construct($message);
    }
}
