<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Returns\Contracts;

/**
 * A return/refund operation was refused by a business rule (unknown order,
 * order not yet paid, quantity exceeds what's left to return, etc.) — carries
 * $errorCode + $params so Presentation can translate via
 * trans("returns::errors.{$errorCode}", $params) instead of the English
 * $message (Application code has no translator; this is the same
 * errorCode+params pattern as Extensions\Contracts\ExtensionInstallationException).
 * Extends \DomainException (not \RuntimeException) so existing callers
 * catching that broader type still work unchanged.
 */
final class ReturnOperationException extends \DomainException
{
    /** @param array<string, string> $params */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $params = [])
    {
        parent::__construct($message);
    }
}
