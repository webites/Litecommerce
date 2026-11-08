<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class PreparedEmail
{
    /** @param list<PreparedEmailAttachment> $attachments */
    public function __construct(
        public string $messageId,
        public string $toEmail,
        public ?string $toName,
        public string $fromEmail,
        public string $fromName,
        public ?string $replyTo,
        public string $subject,
        public string $html,
        public string $text,
        public array $attachments,
    ) {}
}
