<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class EmailTransportResult
{
    public function __construct(public ?string $providerMessageId = null) {}
}
