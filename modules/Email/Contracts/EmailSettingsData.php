<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class EmailSettingsData
{
    public function __construct(
        public string $fromName,
        public string $fromEmail,
        public ?string $replyTo,
        public string $transportKey,
        public string $smtpHost,
        public int $smtpPort,
        public ?string $smtpEncryption,
        public ?string $smtpUsername,
        public bool $smtpPasswordConfigured,
    ) {}
}
