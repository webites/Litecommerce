<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

interface TestEmailSender
{
    /**
     * Sends a one-off message through the configured SMTP transport, bypassing the queue and history.
     *
     * @throws EmailTransportException
     */
    public function send(string $toEmail, string $locale): void;
}
