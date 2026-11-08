<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface TransactionalEmailQueue
{
    public function enqueue(EmailEnvelope $message): ?string;
}
