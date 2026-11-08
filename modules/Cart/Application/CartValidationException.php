<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

/**
 * A business-rule rejection the Storefront controllers turn into a 422
 * with a stable machine-readable code, distinct from field-validation
 * errors (which stay as normal FormRequest 422s). The message is an
 * English developer message; Presentation translates by code and params.
 */
final class CartValidationException extends \RuntimeException
{
    /** @param array<string, scalar|null> $params */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly array $params = [],
    ) {
        parent::__construct($message);
    }
}
