<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Contracts;

final readonly class EmailMessageSummary
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $actorId,
        public ?\DateTimeImmutable $createdAt,
        public ?string $body,
    ) {}
}
