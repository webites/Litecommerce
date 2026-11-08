<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class EmailEnvelope
{
    /**
     * @param  array<string, mixed>  $variables
     * @param  list<EmailAttachmentSource>  $attachments
     */
    public function __construct(
        public string $definitionKey,
        public string $toEmail,
        public ?string $toName,
        public string $locale,
        public array $variables,
        public string $idempotencyKey,
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public string $mode = 'live',
        public array $attachments = [],
        public ?string $transportKey = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
    ) {}
}
