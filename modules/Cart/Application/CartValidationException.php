<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Cart\Application;

/**
 * A business-rule rejection the Storefront controllers turn into a 422
 * with a stable machine-readable code, distinct from field-validation
 * errors (which stay as normal FormRequest 422s).
 */
final class CartValidationException extends \RuntimeException
{
    public readonly string $errorCode;

    public function __construct(string $errorCode, string $message)
    {
        parent::__construct($message);

        $this->errorCode = $errorCode;
    }
}
