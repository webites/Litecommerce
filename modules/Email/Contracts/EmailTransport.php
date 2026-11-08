<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface EmailTransport
{
    public function send(PreparedEmail $email): EmailTransportResult;
}
