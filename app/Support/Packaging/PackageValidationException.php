<?php

declare(strict_types=1);

namespace App\Support\Packaging;

/**
 * A ZIP package (plugin or theme) failed structural validation. $message is
 * a plain English fallback (this runs from Application code with no
 * translator); each of Extensions/Themes re-translates via
 * trans("<module>::errors.{$errorCode}", $params) once it re-wraps this into
 * its own module exception.
 */
final class PackageValidationException extends \RuntimeException
{
    /** @param array<string, string> $params */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $params = [])
    {
        parent::__construct($message);
    }
}
