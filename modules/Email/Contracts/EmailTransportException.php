<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final class EmailTransportException extends \RuntimeException
{
    public function __construct(
        public readonly TransportFailureKind $kind,
        string $message,
        public readonly string $safeCode = 'transport_error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
