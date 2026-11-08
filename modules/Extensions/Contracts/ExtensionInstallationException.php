<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Extensions\Contracts;

// Package upload/validation or one-time install failed — a rejected ZIP, a bad manifest, or a failed
// migration/install() hook. $message is a plain English fallback (this is Domain/Application code, no
// translator available); the presentation layer re-translates via errorCode+params instead of using it.
final class ExtensionInstallationException extends \RuntimeException
{
    /** @param array<string, string> $params */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $params = [])
    {
        parent::__construct($message);
    }
}
