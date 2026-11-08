<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Themes\Contracts;

/**
 * Package upload/validation failed — a rejected ZIP or a bad manifest.
 * $message is a plain English fallback (Domain/Application code has no
 * translator); Presentation re-translates via
 * trans("themes::errors.{$errorCode}", $params) instead of using it — same
 * pattern as Extensions\Contracts\ExtensionInstallationException.
 */
final class ThemeInstallationException extends \RuntimeException
{
    /** @param array<string, string> $params */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $params = [])
    {
        parent::__construct($message);
    }
}
